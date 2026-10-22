<?php

namespace Spatie\LaravelAiTests;

class ToolName
{
    public static function of(string $tool): string
    {
        if (! class_exists($tool)) {
            return $tool;
        }

        if (! method_exists($tool, 'name')) {
            return class_basename($tool);
        }

        return app($tool)->name();
    }
}
