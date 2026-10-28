<?php

namespace Spatie\LaravelAiEvals\Checks;

use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;

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

        return $this->outcome($wordCount <= $this->maxWords, "Answered in {$wordCount} words.");
    }
}
