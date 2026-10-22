<?php

namespace Spatie\LaravelAiTests;

use Laravel\Ai\Tools\ToolNameResolver;

class ToolName
{
    public static function of(string $tool): string
    {
        return class_exists($tool)
            ? ToolNameResolver::resolve(app($tool))
            : $tool;
    }
}
