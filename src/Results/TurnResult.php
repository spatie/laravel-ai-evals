<?php

namespace Spatie\LaravelAiEvals\Results;

use Spatie\LaravelAiEvals\Transcript\Transcript;

class TurnResult
{
    /** @param array<int, CheckOutcome> $outcomes */
    public function __construct(
        public Transcript $transcript,
        public array $outcomes,
        public ?string $judgeError = null,
    ) {}

    public function passed(): bool
    {
        return $this->judgeError === null && ! $this->failed();
    }

    public function failed(): bool
    {
        return collect($this->outcomes)->contains(fn (CheckOutcome $outcome): bool => ! $outcome->passed);
    }

    public function unjudged(): bool
    {
        return $this->judgeError !== null && ! $this->failed();
    }
}
