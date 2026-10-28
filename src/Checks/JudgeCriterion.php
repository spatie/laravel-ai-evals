<?php

namespace Spatie\LaravelAiEvals\Checks;

class JudgeCriterion implements Expectation
{
    public function __construct(
        public string $criterion,
    ) {}

    public function label(): string
    {
        return $this->criterion;
    }
}
