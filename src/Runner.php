<?php

namespace Spatie\LaravelAiEvals;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\Agent;
use LogicException;
use Spatie\LaravelAiEvals\Checks\Check;
use Spatie\LaravelAiEvals\Drivers\AgentDriver;
use Spatie\LaravelAiEvals\Drivers\Driver;
use Spatie\LaravelAiEvals\Judge\Judge;
use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Results\EvaluationResult;
use Spatie\LaravelAiEvals\Results\RunResult;
use Spatie\LaravelAiEvals\Results\TurnResult;
use Spatie\LaravelAiEvals\Transcript\Transcript;
use Throwable;

class Runner
{
    public function __construct(
        protected Judge $judge,
    ) {}

    public function run(Evaluation $evaluation): EvaluationResult
    {
        $evaluation->ensureRunnable();

        $runCount = $evaluation->runCount();

        $this->skipConversationTitles();

        $driver = $this->resolveDriver($evaluation);

        $prompts = collect($evaluation->turns)
            ->map(fn (Turn $turn): string => $this->callWithDriver($turn->prompt, $driver))
            ->all();

        $expectations = $this->resolveExpectations($evaluation, $driver);

        $result = new EvaluationResult;

        while (! $result->isSettled($runCount)) {
            $result->runs[] = $this->runOnce($evaluation, $driver, $prompts, $expectations);
        }

        return $result;
    }

    protected function skipConversationTitles(): void
    {
        // A generated title costs an extra model call per run and no check reads it.
        config()->set('ai.conversations.generate_title', false);
    }

    protected function resolveDriver(Evaluation $evaluation): Driver
    {
        $agent = $evaluation->agent instanceof Closure
            ? app()->call($evaluation->agent)
            : $evaluation->agent;

        return match (true) {
            $agent instanceof Driver => $agent,
            $agent instanceof Agent => new AgentDriver(fn (): Agent => clone $agent),
            is_string($agent) && is_subclass_of($agent, Agent::class) => new AgentDriver($agent),
            default => throw new LogicException("The agent of the evaluation \"{$evaluation->description}\" must be a ".Driver::class.', an '.Agent::class.' or an agent class, but is '.get_debug_type($agent).'.'),
        };
    }

    /** @param array<string, mixed> $parameters */
    protected function callWithDriver(mixed $value, Driver $driver, array $parameters = []): mixed
    {
        if (! $value instanceof Closure) {
            return $value;
        }

        return app()->call($value, [...$parameters, $driver::class => $driver, 'driver' => $driver]);
    }

    /** @return array<int, array<int, Check|string>> */
    protected function resolveExpectations(Evaluation $evaluation, Driver $driver): array
    {
        $resolve = fn (mixed $value, array $parameters = []): mixed => $this->callWithDriver($value, $driver, $parameters);

        try {
            return collect($evaluation->turns)
                ->map(fn (Turn $turn): array => $turn->resolveExpectations($resolve))
                ->all();
        } catch (Throwable $exception) {
            throw new LogicException("The evaluation \"{$evaluation->description}\" has a check that can't be set up. {$exception->getMessage()}", previous: $exception);
        }
    }

    /**
     * @param  array<int, string>  $prompts
     * @param  array<int, array<int, Check|string>>  $expectations
     */
    protected function runOnce(
        Evaluation $evaluation,
        Driver $driver,
        array $prompts,
        array $expectations,
    ): RunResult {
        $run = new RunResult;
        $unfinishedTurn = null;
        $transactionLevel = DB::transactionLevel();

        try {
            DB::beginTransaction();

            $driver->startConversation();

            foreach ($evaluation->turns as $index => $turn) {
                $unfinishedTurn = new Transcript($prompts[$index]);

                $this->converse($driver, $turn, $unfinishedTurn);

                $turnResult = $this->evaluate($expectations[$index], $unfinishedTurn, Arr::pluck($run->turns, 'transcript'));

                $run->turns[] = $turnResult;
                $unfinishedTurn = null;

                if ($turnResult->failed()) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            $run->crash = $this->describe($exception);
            $run->crashedTurn = $unfinishedTurn;
        } finally {
            DB::rollBack($transactionLevel);
        }

        return $run;
    }

    protected function converse(Driver $driver, Turn $turn, Transcript $transcript): void
    {
        $response = $driver->prompt($transcript->prompt);

        $transcript->addResponse($response);

        $maxRounds = 5;

        for ($round = 1; $response->pendingApprovals->isNotEmpty(); $round++) {
            if ($round > $maxRounds) {
                throw new LogicException("The agent kept asking for approval after {$maxRounds} rounds.");
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
     * @param  array<int, Check|string>  $expectations
     * @param  array<int, Transcript>  $earlierTurns
     */
    protected function evaluate(array $expectations, Transcript $transcript, array $earlierTurns): TurnResult
    {
        [$checks, $criteria] = collect($expectations)->partition(fn (Check|string $expectation): bool => $expectation instanceof Check);

        $outcomes = $checks
            ->map(fn (Check $check): CheckOutcome => $check->evaluate($transcript))
            ->values()
            ->all();

        $turnResult = new TurnResult($transcript, $outcomes);

        if ($turnResult->failed()) {
            return $turnResult;
        }

        try {
            $judgeOutcomes = $this->judge->evaluate($criteria->values()->all(), $transcript, $earlierTurns);
        } catch (Throwable $exception) {
            return new TurnResult($transcript, $outcomes, judgeError: $this->describe($exception));
        }

        return new TurnResult($transcript, [...$outcomes, ...$judgeOutcomes]);
    }

    protected function describe(Throwable $exception): string
    {
        return $exception::class.': '.$exception->getMessage();
    }
}
