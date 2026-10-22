<?php

namespace Spatie\LaravelAiTests\Results;

use Illuminate\Support\Arr;
use Spatie\LaravelAiTests\Checks\Expectation;
use Spatie\LaravelAiTests\Transcript\Transcript;

class TurnResult
{
    /** @param array<int, CheckOutcome> $outcomes */
    public function __construct(
        public Transcript $transcript,
        public array $outcomes,
        public ?string $judgeModel = null,
    ) {}

    public function passed(): bool
    {
        foreach ($this->outcomes as $outcome) {
            if (! $outcome->passed) {
                return false;
            }
        }

        return true;
    }

    public function outcomeFor(Expectation $expectation): ?CheckOutcome
    {
        return Arr::first($this->outcomes, fn (CheckOutcome $outcome): bool => $outcome->expectation === $expectation);
    }
}
