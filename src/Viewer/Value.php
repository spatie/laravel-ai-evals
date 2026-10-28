<?php

namespace Spatie\LaravelAiTests\Viewer;

class Value
{
    public static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /** @return array<mixed> */
    public static function array(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
