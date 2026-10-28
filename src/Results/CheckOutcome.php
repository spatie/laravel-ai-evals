<?php

namespace Spatie\LaravelAiTests\Results;

use Spatie\LaravelAiTests\Checks\Expectation;
use Spatie\LaravelAiTests\Checks\RubricCriterion;

class CheckOutcome
{
    public function __construct(
        public Expectation $expectation,
        public bool $passed,
        public ?string $reason = null,
    ) {}

    public function label(): string
    {
        return $this->expectation->label();
    }

    public function judged(): bool
    {
        return $this->expectation instanceof RubricCriterion;
    }
}
