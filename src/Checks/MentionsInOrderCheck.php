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
        $quoted = array_map(fn (string $needle): string => static::quote($needle), $this->needles);

        return 'Mentions '.implode(', then ', $quoted);
    }

    public function kind(): ExpectationKind
    {
        return ExpectationKind::Mentions;
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
        $offset = 0;

        foreach ($this->needles as $needle) {
            $position = mb_stripos($answer, $needle, $offset);

            if ($position !== false) {
                $previousNeedle = $needle;
                $offset = $position + 1;

                continue;
            }

            if (mb_stripos($answer, $needle) === false) {
                return 'Does not mention '.static::quote($needle).'.';
            }

            return 'Mentions '.static::quote($needle).' before '.static::quote($previousNeedle).'.';
        }

        return null;
    }
}
