<?php

namespace Spatie\LaravelAiTests\Checks;

use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

class MentionsCheck extends Check
{
    protected const MaxLabelLength = 60;

    public function __construct(
        protected string $needle,
    ) {}

    public static function quote(string $needle): string
    {
        if (Str::length($needle) <= static::MaxLabelLength) {
            return "`{$needle}`";
        }

        return '`'.Str::substr($needle, 0, 25).'…'.Str::substr($needle, -34).'`';
    }

    public function label(): string
    {
        return 'Mentions '.static::quote($this->needle);
    }

    public function kind(): string
    {
        return 'mentions';
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $mentioned = str_contains($transcript->answer(), $this->needle);

        return new CheckOutcome(
            expectation: $this,
            passed: $mentioned,
            reason: $mentioned ? null : "Does not mention {$this->needle}.",
        );
    }
}
