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

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $wordCount = $transcript->wordCount();

        return new CheckOutcome(
            label: $this->label(),
            passed: $wordCount <= $this->maxWords,
            reason: $wordCount <= $this->maxWords ? null : "Answered in {$wordCount} words.",
        );
    }
}
