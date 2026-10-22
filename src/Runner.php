<?php

namespace Spatie\LaravelAiTests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\PendingApproval;
use LogicException;
use Spatie\LaravelAiTests\Checks\Check;
use Spatie\LaravelAiTests\Drivers\Driver;
use Spatie\LaravelAiTests\Judge\Judge;
use Spatie\LaravelAiTests\Results\RunResult;
use Spatie\LaravelAiTests\Results\TestResult;
use Spatie\LaravelAiTests\Results\TurnResult;
use Spatie\LaravelAiTests\Transcript\Transcript;
use Throwable;

class Runner
{
    protected const MaxApprovalRounds = 5;

    public function __construct(
        protected Judge $judge,
    ) {}

    public function run(AiTest $aiTest): TestResult
    {
        $aiTest->ensureRunnable();

        $ranAt = CarbonImmutable::now();

        $driver = $this->resolveDriver($aiTest);

        $prompts = array_map(
            fn (Turn $turn): string => $this->resolvePrompt($turn, $driver),
            $aiTest->turns,
        );

        $runs = array_map(
            fn (int $number): RunResult => $this->runOnce($aiTest, $driver, $prompts, $number),
            range(1, $aiTest->runCount()),
        );

        return new TestResult(
            aiTest: $aiTest,
            prompts: $prompts,
            runs: $runs,
            minPassRate: $aiTest->requiredPassRate(),
            ranAt: $ranAt,
        );
    }

    protected function resolveDriver(AiTest $aiTest): Driver
    {
        $driver = $aiTest->driver instanceof Closure
            ? app()->call($aiTest->driver)
            : $aiTest->driver;

        if (! $driver instanceof Driver) {
            throw new LogicException("The driver of the AI test \"{$aiTest->description}\" must implement ".Driver::class.'.');
        }

        return $driver;
    }

    protected function resolvePrompt(Turn $turn, Driver $driver): string
    {
        if (! $turn->prompt instanceof Closure) {
            return $turn->prompt;
        }

        return app()->call($turn->prompt, [$driver::class => $driver, 'driver' => $driver]);
    }

    /** @param array<int, string> $prompts */
    protected function runOnce(
        AiTest $aiTest,
        Driver $driver,
        array $prompts,
        int $number,
    ): RunResult {
        $run = new RunResult($number);
        $unfinishedTurn = null;

        DB::beginTransaction();

        try {
            $driver->startConversation();

            foreach ($aiTest->turns as $index => $turn) {
                $unfinishedTurn = new Transcript($prompts[$index]);

                $this->converse($driver, $turn, $unfinishedTurn);

                $run->turns[] = $this->evaluate($turn, $unfinishedTurn, array_column($run->turns, 'transcript'));

                $unfinishedTurn = null;
            }
        } catch (Throwable $exception) {
            $run->crash = $this->describe($exception);
            $run->crashedTurn = $unfinishedTurn;
        } finally {
            DB::rollBack();
        }

        return $run;
    }

    protected function converse(Driver $driver, Turn $turn, Transcript $transcript): void
    {
        $response = $driver->prompt($transcript->prompt);

        $transcript->addResponse($response);

        for ($round = 1; $response->pendingApprovals->isNotEmpty(); $round++) {
            if ($round > static::MaxApprovalRounds) {
                throw new LogicException('The agent kept asking for approval after '.static::MaxApprovalRounds.' rounds.');
            }

            $decisions = $response->pendingApprovals
                ->mapWithKeys(fn (PendingApproval $approval): array => [$approval->id => $turn->approvesAll])
                ->all();

            $transcript->recordDecisions($decisions);

            $response = $driver->decide($decisions);

            $transcript->addResponse($response);
        }
    }

    /** @param array<int, Transcript> $earlierTurns */
    protected function evaluate(Turn $turn, Transcript $transcript, array $earlierTurns): TurnResult
    {
        $outcomes = array_map(
            fn (Check $check) => $check->evaluate($transcript),
            $turn->checks,
        );

        try {
            $judgeResult = $this->judge->evaluate($turn->rubric, $transcript, $earlierTurns);
        } catch (Throwable $exception) {
            return new TurnResult($transcript, $outcomes, judgeError: $this->describe($exception));
        }

        return new TurnResult(
            transcript: $transcript,
            outcomes: [...$outcomes, ...$judgeResult->outcomes],
            judgeModel: $judgeResult->model,
        );
    }

    protected function describe(Throwable $exception): string
    {
        return $exception::class.': '.$exception->getMessage();
    }
}
