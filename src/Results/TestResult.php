<?php

namespace Spatie\LaravelAiTests\Results;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Spatie\LaravelAiTests\AiTest;

class TestResult
{
    /**
     * @param  array<int, string>  $prompts
     * @param  array<int, RunResult>  $runs
     * @param  array<string, string>  $context
     */
    public function __construct(
        public AiTest $aiTest,
        public array $prompts,
        public array $runs,
        public float $minPassRate,
        public CarbonImmutable $ranAt,
        public array $context = [],
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

    /** @return array<int, string> */
    public function errors(): array
    {
        return array_merge(...array_map(fn (RunResult $run): array => $run->errors(), $this->runs));
    }

    public function model(): ?string
    {
        $turnResult = Arr::first(
            $this->turnResults(),
            fn (TurnResult $turnResult): bool => $turnResult->transcript->model !== null,
        );

        return $turnResult?->transcript->model;
    }

    public function judgeModel(): ?string
    {
        $turnResult = Arr::first(
            $this->turnResults(),
            fn (TurnResult $turnResult): bool => $turnResult->judgeModel !== null,
        );

        return $turnResult?->judgeModel;
    }

    /** @return array<int, TurnResult> */
    protected function turnResults(): array
    {
        return array_merge(...array_column($this->runs, 'turns'));
    }
}
