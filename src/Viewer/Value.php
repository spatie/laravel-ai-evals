<?php

namespace Spatie\LaravelAiTests\Viewer;

class Value
{
    public static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
