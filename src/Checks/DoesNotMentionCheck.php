<?php

namespace Spatie\LaravelAiTests\Checks;

use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

class DoesNotMentionCheck extends Check
{
    public function __construct(
        protected string $needle,
    ) {}

    public function label(): string
    {
        return 'Does not mention '.MentionsCheck::quote($this->needle);
    }

    public function kind(): string
    {
        return 'mentions';
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $match = $this->firstMatch($transcript->answer());

        return new CheckOutcome(
            expectation: $this,
            passed: $match === null,
            reason: $match === null ? null : 'Mentions '.MentionsCheck::quote($match).'.',
        );
    }

    protected function firstMatch(string $answer): ?string
    {
        $position = mb_stripos($answer, $this->needle);

        if ($position === false) {
            return null;
        }

        return mb_substr($answer, $position, mb_strlen($this->needle));
    }
}
