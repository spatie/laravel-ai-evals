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
