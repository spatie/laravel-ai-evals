<?php

namespace Spatie\LaravelAiTests\Results;

class RunResult
{
    /** @var array<int, TurnResult> */
    public array $turns = [];

    public ?string $crash = null;

    public function __construct(
        public int $number,
    ) {}

    public function passed(): bool
    {
        if ($this->crash !== null) {
            return false;
        }

        foreach ($this->turns as $turn) {
            if (! $turn->passed()) {
                return false;
            }
        }

        return true;
    }
}
