<?php

namespace Spatie\LaravelAiEvals;

use LogicException;
use Throwable;

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

        try {
            $instance = app($tool);
        } catch (Throwable $exception) {
            throw new LogicException("Could not create the tool {$tool} to read its name: {$exception->getMessage()}", previous: $exception);
        }

        return is_callable([$instance, 'name']) ? $instance->name() : class_basename($tool);
    }
}
