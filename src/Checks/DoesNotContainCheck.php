<?php

namespace Spatie\LaravelAiEvals\Checks;

use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;

class DoesNotContainCheck extends Check
{
    public function __construct(
        protected string $needle,
    ) {}

    public function label(): string
    {
        return 'Does not contain '.static::quote($this->needle);
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $match = $this->firstMatch($transcript->answer());

        return new CheckOutcome(
            $this->label(),
            passed: $match === null,
            reason: $match === null ? null : 'Contains '.static::quote($match).'.',
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
