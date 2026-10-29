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
            && collect($this->turns)->every(fn (TurnResult $turn): bool => $turn->passed());
    }

    public function unjudged(): bool
    {
        if ($this->crash !== null) {
            return false;
        }

        $turns = collect($this->turns);

        return $turns->doesntContain(fn (TurnResult $turn): bool => $turn->failed())
            && $turns->contains(fn (TurnResult $turn): bool => $turn->unjudged());
    }
}
