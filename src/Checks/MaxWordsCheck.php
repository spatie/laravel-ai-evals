<?php

namespace Spatie\LaravelAiTests\Checks;

use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

class MaxWordsCheck extends Check
{
    public function __construct(
        protected int $maxWords,
    ) {}

    public function label(): string
    {
        return "At most {$this->maxWords} words";
    }

    public function kind(): ExpectationKind
    {
        return ExpectationKind::MaxWords;
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $wordCount = $transcript->wordCount();

        $passed = $wordCount <= $this->maxWords;

        return new CheckOutcome(
            expectation: $this,
            passed: $passed,
            reason: $passed ? null : "Answered in {$wordCount} words.",
        );
    }
}
