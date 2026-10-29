<?php

namespace Spatie\LaravelAiEvals\Results;

class EvaluationResult
{
    /** @param array<int, RunResult> $runs */
    public function __construct(
        public array $runs = [],
    ) {}

    public function passedRuns(): int
    {
        return collect($this->runs)
            ->filter(fn (RunResult $run): bool => $run->passed())
            ->count();
    }

    public function unjudgedRuns(): int
    {
        return collect($this->runs)
            ->filter(fn (RunResult $run): bool => $run->unjudged())
            ->count();
    }

    public function judgedRuns(): int
    {
        return count($this->runs) - $this->unjudgedRuns();
    }

    public function isSettled(int $runCount): bool
    {
        return count($this->runs) === $runCount || $this->hasFailedRun();
    }

    public function passed(): bool
    {
        return $this->judgedRuns() > 0 && ! $this->hasFailedRun();
    }

    protected function hasFailedRun(): bool
    {
        return $this->passedRuns() < $this->judgedRuns();
    }
}
