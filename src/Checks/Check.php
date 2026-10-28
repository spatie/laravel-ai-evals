<?php

namespace Spatie\LaravelAiTests\Checks;

use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

abstract class Check implements Expectation
{
    abstract public function evaluate(Transcript $transcript): CheckOutcome;

    protected static function quote(string $value): string
    {
        if (Str::length($value) <= 60) {
            return "`{$value}`";
        }

        return '`'.Str::substr($value, 0, 25).'…'.Str::substr($value, -34).'`';
    }
}
