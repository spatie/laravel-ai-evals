<?php

namespace Spatie\LaravelAiEvals\Checks;

use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;

class ContainsInOrderCheck extends Check
{
    /** @param array<int, string> $needles */
    public function __construct(
        protected array $needles,
    ) {}

    public function label(): string
    {
        $quoted = array_map(fn (string $needle): string => static::quote($needle), $this->needles);

        return 'Contains '.implode(', then ', $quoted);
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $reason = $this->failureReason($transcript->answer());

        return new CheckOutcome($this->label(), passed: $reason === null, reason: $reason);
    }

    protected function failureReason(string $answer): ?string
    {
        $previousNeedle = null;
        $offset = 0;

        foreach ($this->needles as $needle) {
            $position = mb_stripos($answer, $needle, $offset);

            if ($position !== false) {
                $previousNeedle = $needle;
                $offset = $position + mb_strlen($needle);

                continue;
            }

            if (mb_stripos($answer, $needle) === false) {
                return 'Does not contain '.static::quote($needle).'.';
            }

            return 'Contains '.static::quote($needle).' before '.static::quote($previousNeedle).'.';
        }

        return null;
    }
}
