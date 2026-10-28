<?php

namespace Spatie\LaravelAiEvals\Results;

class EvaluationResult
{
    /** @param array<int, RunResult> $runs */
    public function __construct(
        public array $runs,
        public float $minPassRate,
    ) {}

    public function passedRuns(): int
    {
        return count(array_filter($this->runs, fn (RunResult $run): bool => $run->passed()));
    }

    public function unjudgedRuns(): int
    {
        return count(array_filter($this->runs, fn (RunResult $run): bool => $run->unjudged()));
    }

    public function judgedRuns(): int
    {
        return count($this->runs) - $this->unjudgedRuns();
    }

    public function passRate(): float
    {
        if ($this->judgedRuns() === 0) {
            return 0.0;
        }

        return $this->passedRuns() / $this->judgedRuns();
    }

    public function passed(): bool
    {
        return $this->judgedRuns() > 0 && $this->passRate() >= $this->minPassRate;
    }
}
