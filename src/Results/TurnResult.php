<?php

namespace Spatie\LaravelAiTests\Results;

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
}
