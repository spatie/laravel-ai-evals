<?php

namespace Spatie\LaravelAiEvals\Checks;

use Illuminate\Support\Str;
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
        $failureReason = $this->failureReason($transcript->answer());

        return $this->outcome($failureReason === null, $failureReason);
    }

    protected function failureReason(string $answer): ?string
    {
        $position = mb_stripos($answer, $this->needle);

        if ($position === false) {
            return null;
        }

        $match = Str::substr($answer, $position, Str::length($this->needle));

        return 'Contains '.static::quote($match).'.';
    }
}
