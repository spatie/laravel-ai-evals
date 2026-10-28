<?php

namespace Spatie\LaravelAiEvals\Results;

use Spatie\LaravelAiEvals\Transcript\Transcript;

class RunResult
{
    /** @var array<int, TurnResult> */
    public array $turns = [];

    public ?string $crash = null;

    public ?Transcript $crashedTurn = null;

    public function passed(): bool
    {
        return $this->crash === null
            && array_filter($this->turns, fn (TurnResult $turn): bool => ! $turn->passed()) === [];
    }

    public function unjudged(): bool
    {
        if ($this->crash !== null) {
            return false;
        }

        return array_filter($this->turns, fn (TurnResult $turn): bool => $turn->failed()) === []
            && array_filter($this->turns, fn (TurnResult $turn): bool => $turn->unjudged()) !== [];
    }
}
