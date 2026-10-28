<?php

namespace Spatie\LaravelAiEvals;

use Closure;
use Laravel\Ai\Contracts\Agent;
use LogicException;
use PHPUnit\Framework\Assert;
use Spatie\LaravelAiEvals\Drivers\Driver;
use Spatie\LaravelAiEvals\Results\Report;

class Evaluation
{
    /** @var Driver|Agent|class-string<Agent>|Closure|null */
    public Driver|Agent|Closure|string|null $agent = null;

    public ?int $repeat = null;

    public ?float $minPassRate = null;

    /** @var array<int, Turn> */
    public array $turns = [];

    public function __construct(
        public string $description,
    ) {}

    /** @param Driver|Agent|class-string<Agent>|Closure $agent */
    public function agent(Driver|Agent|Closure|string $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function repeat(int $times, ?float $minPassRate = null): static
    {
        $this->repeat = $times;
        $this->minPassRate = $minPassRate;

        return $this;
    }

    public function prompt(string|Closure $prompt): Turn
    {
        $turn = new Turn($this, $prompt);

        $this->turns[] = $turn;

        return $turn;
    }

    public function ensureRunnable(): void
    {
        if ($this->agent === null) {
            throw new LogicException("The evaluation \"{$this->description}\" has no agent. Call ->agent().");
        }

        if ($this->turns === []) {
            throw new LogicException("The evaluation \"{$this->description}\" has no prompts. Call ->prompt().");
        }

        $repeat = AiEvals::repeatCount($this->repeat);

        if ($repeat < 1) {
            throw new LogicException("The evaluation \"{$this->description}\" needs to run at least once, but repeats {$repeat} times.");
        }

        $minPassRate = AiEvals::minPassRate($this->minPassRate);

        if ($minPassRate < 0 || $minPassRate > 1) {
            throw new LogicException("The minimum pass rate of the evaluation \"{$this->description}\" must be between 0 and 1, but is {$minPassRate}.");
        }
    }

    public function execute(): void
    {
        if (! Plugin::isEvalMode()) {
            Assert::markTestSkipped('Eval skipped. Run with [--evals] to evaluate against a real model.');
        }

        $result = app(Runner::class)->run($this);

        if (! $result->passed()) {
            Assert::fail((new Report($result))->render());
        }

        Assert::assertTrue($result->passed());
    }
}
