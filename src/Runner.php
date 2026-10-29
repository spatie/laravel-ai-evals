<?php

namespace Spatie\LaravelAiEvals;

use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\Agent;
use LogicException;
use Spatie\LaravelAiEvals\Checks\Check;
use Spatie\LaravelAiEvals\Checks\Expectation;
use Spatie\LaravelAiEvals\Checks\JudgeCriterion;
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
    protected const MaxApprovalRounds = 5;

    public function __construct(
        protected Judge $judge,
    ) {}

    public function run(Evaluation $evaluation): EvaluationResult
    {
        $evaluation->ensureRunnable();

        $this->skipConversationTitles();

        $driver = $this->resolveDriver($evaluation);

        $prompts = array_map(
            fn (Turn $turn): string => $this->callWithDriver($turn->prompt, $driver),
            $evaluation->turns,
        );

        $expectations = $this->resolveExpectations($evaluation, $driver);

        $runs = array_map(
            fn (): RunResult => $this->runOnce($evaluation, $driver, $prompts, $expectations),
            range(1, AiEvals::repeatCount($evaluation->repeat)),
        );

        return new EvaluationResult($runs, AiEvals::minPassRate($evaluation->minPassRate));
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

    /** @return array<int, array<int, Expectation>> */
    protected function resolveExpectations(Evaluation $evaluation, Driver $driver): array
    {
        $resolve = fn (mixed $value, array $parameters = []): mixed => $this->callWithDriver($value, $driver, $parameters);

        try {
            return array_map(
                fn (Turn $turn): array => $turn->resolveExpectations($resolve),
                $evaluation->turns,
            );
        } catch (Throwable $exception) {
            throw new LogicException("The evaluation \"{$evaluation->description}\" has a check that can't be set up. {$exception->getMessage()}", previous: $exception);
        }
    }

    /**
     * @param  array<int, string>  $prompts
     * @param  array<int, array<int, Expectation>>  $expectations
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

                $run->turns[] = $this->evaluate($expectations[$index], $unfinishedTurn, array_column($run->turns, 'transcript'));

                $unfinishedTurn = null;
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
        $criteria = array_filter($expectations, fn (Expectation $expectation): bool => $expectation instanceof JudgeCriterion);

        $outcomes = array_values(array_map(
            fn (Check $check): CheckOutcome => $check->evaluate($transcript),
            $checks,
        ));

        try {
            $judgeOutcomes = $this->judge->evaluate(array_values($criteria), $transcript, $earlierTurns);
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
