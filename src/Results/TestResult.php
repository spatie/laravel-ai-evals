<?php

namespace Spatie\LaravelAiTests\Results;

use Carbon\CarbonImmutable;
use Spatie\LaravelAiTests\AiTest;

class TestResult
{
    /**
     * @param  array<int, string>  $prompts
     * @param  array<int, RunResult>  $runs
     */
    public function __construct(
        public AiTest $aiTest,
        public array $prompts,
        public array $runs,
        public float $minPassRate,
        public CarbonImmutable $ranAt,
    ) {}

    public function passedRuns(): int
    {
        return count(array_filter($this->runs, fn (RunResult $run): bool => $run->passed()));
    }

    public function passRate(): float
    {
        return round($this->passedRuns() / count($this->runs), 2);
    }

    public function passed(): bool
    {
        return $this->passRate() >= $this->minPassRate;
    }

    public function exampleRun(): RunResult
    {
        foreach ($this->runs as $run) {
            if (! $run->passed()) {
                return $run;
            }
        }

        return $this->runs[0];
    }

    public function model(): ?string
    {
        foreach ($this->turnResults() as $turnResult) {
            if ($turnResult->transcript->model !== null) {
                return $turnResult->transcript->model;
            }
        }

        return null;
    }

    public function judgeModel(): ?string
    {
        foreach ($this->turnResults() as $turnResult) {
            if ($turnResult->judgeModel !== null) {
                return $turnResult->judgeModel;
            }
        }

        return null;
    }

    /** @return array<int, TurnResult> */
    protected function turnResults(): array
    {
        return array_merge(...array_map(fn (RunResult $run): array => $run->turns, $this->runs));
    }
}
