<?php

namespace Spatie\LaravelAiTests\Results;

use LogicException;

class ResultSlugs
{
    /** @var array<string, string> */
    protected static array $locations = [];

    public static function claim(string $slug, string $location): void
    {
        $claimedAt = static::$locations[$slug] ?? $location;

        if ($claimedAt !== $location) {
            throw new LogicException("The AI tests at {$claimedAt} and {$location} would both write their result to {$slug}.yaml. Give one of them a different description.");
        }

        static::$locations[$slug] = $location;
    }
}
