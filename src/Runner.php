<?php

namespace Spatie\LaravelAiTests;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\PendingApproval;
use LogicException;
use Spatie\LaravelAiTests\Checks\Check;
use Spatie\LaravelAiTests\Checks\Expectation;
use Spatie\LaravelAiTests\Checks\RubricCriterion;
use Spatie\LaravelAiTests\Drivers\DescribesContext;
use Spatie\LaravelAiTests\Drivers\Driver;
use Spatie\LaravelAiTests\Judge\Judge;
use Spatie\LaravelAiTests\Results\CheckOutcome;
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

        $context = $driver instanceof DescribesContext ? $driver->context() : [];

        $prompts = array_map(
            fn (Turn $turn): string => $this->callWithDriver($turn->prompt, $driver),
            $aiTest->turns,
        );

        $expectations = $this->resolveExpectations($aiTest, $driver);

        $runs = array_map(
            fn (int $number): RunResult => $this->runOnce(
                $aiTest,
                $driver,
                $prompts,
                $expectations,
                $number,
            ),
            range(1, $aiTest->runCount()),
        );

        return new TestResult(
            aiTest: $aiTest,
            prompts: $prompts,
            expectations: $expectations,
            runs: $runs,
            minPassRate: $aiTest->requiredPassRate(),
            ranAt: $ranAt,
            context: $context,
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

    protected function callWithDriver(mixed $value, Driver $driver): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        return app()->call($value, [$driver::class => $driver, 'driver' => $driver]);
    }

    /** @return array<int, array<int, Expectation>> */
    protected function resolveExpectations(AiTest $aiTest, Driver $driver): array
    {
        try {
            return array_map(
                fn (Turn $turn): array => $this->ensureLabelled(
                    $turn->resolveExpectations(fn (mixed $value): mixed => $this->callWithDriver($value, $driver)),
                ),
                $aiTest->turns,
            );
        } catch (Throwable $exception) {
            throw new LogicException("The AI test \"{$aiTest->description}\" has a check that can't be set up. {$exception->getMessage()}", previous: $exception);
        }
    }

    /**
     * @param  array<int, Expectation>  $expectations
     * @return array<int, Expectation>
     */
    protected function ensureLabelled(array $expectations): array
    {
        foreach ($expectations as $expectation) {
            $expectation->label();
        }

        return $expectations;
    }

    /**
     * @param  array<int, string>  $prompts
     * @param  array<int, array<int, Expectation>>  $expectations
     */
    protected function runOnce(
        AiTest $aiTest,
        Driver $driver,
        array $prompts,
        array $expectations,
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

                $run->turns[] = $this->evaluate($expectations[$index], $unfinishedTurn, array_column($run->turns, 'transcript'));

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

    /**
     * @param  array<int, Expectation>  $expectations
     * @param  array<int, Transcript>  $earlierTurns
     */
    protected function evaluate(array $expectations, Transcript $transcript, array $earlierTurns): TurnResult
    {
        $checks = array_filter($expectations, fn (Expectation $expectation): bool => $expectation instanceof Check);
        $rubric = array_filter($expectations, fn (Expectation $expectation): bool => $expectation instanceof RubricCriterion);

        $outcomes = array_values(array_map(
            fn (Check $check): CheckOutcome => $check->evaluate($transcript),
            $checks,
        ));

        try {
            $judgeResult = $this->judge->evaluate(array_values($rubric), $transcript, $earlierTurns);
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
