<?php

namespace Spatie\LaravelAiEvals\Checks;

use Illuminate\Support\Str;
use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;

abstract class Check implements Expectation
{
    abstract public function evaluate(Transcript $transcript): CheckOutcome;

    protected function outcome(bool $passed, string $failureReason): CheckOutcome
    {
        return new CheckOutcome($this->label(), $passed, $passed ? null : $failureReason);
    }

    protected static function quote(string $value): string
    {
        if (Str::length($value) <= 60) {
            return "`{$value}`";
        }

        return '`'.Str::substr($value, 0, 25).'…'.Str::substr($value, -34).'`';
    }
}
