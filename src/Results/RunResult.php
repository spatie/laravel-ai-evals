<?php

namespace Spatie\LaravelAiTests\Results;

use Spatie\LaravelAiTests\Transcript\Transcript;

class RunResult
{
    /** @var array<int, TurnResult> */
    public array $turns = [];

    public ?string $crash = null;

    public ?Transcript $crashedTurn = null;

    public function __construct(
        public int $number,
    ) {}

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

        return ! $turns->contains(fn (TurnResult $turn): bool => $turn->failed())
            && $turns->contains(fn (TurnResult $turn): bool => $turn->unjudged());
    }

    /** @return array<int, string> */
    public function errors(): array
    {
        $errors = $this->crash !== null ? ["Run {$this->number} crashed: {$this->crash}"] : [];

        foreach ($this->turns as $index => $turn) {
            if ($turn->judgeError !== null) {
                $errors[] = "The judge failed on run {$this->number}, turn ".($index + 1).": {$turn->judgeError}";
            }
        }

        return $errors;
    }
}
