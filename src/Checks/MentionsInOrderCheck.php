<?php

namespace Spatie\LaravelAiTests\Checks;

use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

class MentionsInOrderCheck extends Check
{
    /** @param array<int, string> $needles */
    public function __construct(
        protected array $needles,
    ) {}

    public function label(): string
    {
        $quoted = array_map(fn (string $needle): string => MentionsCheck::quote($needle), $this->needles);

        return 'Mentions '.implode(', then ', $quoted);
    }

    public function kind(): string
    {
        return 'mentions';
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $reason = $this->failureReason($transcript->answer());

        return new CheckOutcome(
            expectation: $this,
            passed: $reason === null,
            reason: $reason,
        );
    }

    protected function failureReason(string $answer): ?string
    {
        $previousNeedle = null;
        $previousPosition = -1;

        foreach ($this->needles as $needle) {
            $position = mb_strpos($answer, $needle);

            if ($position === false) {
                return "Does not mention {$needle}.";
            }

            if ($position <= $previousPosition) {
                return "Mentions {$needle} before {$previousNeedle}.";
            }

            $previousNeedle = $needle;
            $previousPosition = $position;
        }

        return null;
    }
}
