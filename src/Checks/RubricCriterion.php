<?php

namespace Spatie\LaravelAiTests\Checks;

class RubricCriterion implements Expectation
{
    public function __construct(
        public string $criterion,
    ) {}

    public function label(): string
    {
        return $this->criterion;
    }

    public function kind(): ExpectationKind
    {
        return ExpectationKind::Rubric;
    }
}
