<?php

namespace Spatie\LaravelAiTests\Checks;

use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

class MentionsCheck extends Check
{
    public function __construct(
        protected string $needle,
    ) {}

    public function label(): string
    {
        return 'Mentions '.static::quote($this->needle);
    }

    public function kind(): ExpectationKind
    {
        return ExpectationKind::Mentions;
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $mentioned = Str::contains($transcript->answer(), $this->needle, ignoreCase: true);

        return new CheckOutcome(
            expectation: $this,
            passed: $mentioned,
            reason: $mentioned ? null : 'Does not mention '.static::quote($this->needle).'.',
        );
    }
}
