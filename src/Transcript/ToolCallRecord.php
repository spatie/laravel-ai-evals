<?php

namespace Spatie\LaravelAiEvals\Transcript;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class ToolCallRecord
{
    public const ShortArgumentsLength = 200;

    public ?string $result = null;

    public ToolCallStatus $status = ToolCallStatus::NoResult;

    public ?bool $approved = null;

    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $name,
        public array $arguments,
    ) {}

    /** @param array<string, mixed>|Closure(array<string, mixed>): mixed|null $arguments */
    public function hasArguments(array|Closure|null $arguments): bool
    {
        if ($arguments === null) {
            return true;
        }

        if ($arguments instanceof Closure) {
            try {
                return (bool) $arguments($this->arguments);
            } catch (Throwable) {
                return false;
            }
        }

        $actual = Arr::dot($this->arguments);

        foreach (Arr::dot($arguments) as $key => $value) {
            if (! array_key_exists($key, $actual) || $actual[$key] !== $value) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $arguments */
    public static function encodeArguments(array $arguments, ?int $limit = null): string
    {
        $encoded = (string) json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $limit === null ? $encoded : Str::limit($encoded, $limit);
    }

    public function encodedArguments(?int $limit = null): string
    {
        return static::encodeArguments($this->arguments, $limit);
    }

    public function decisionLabel(): ?string
    {
        return match ($this->approved) {
            true => 'approved',
            false => 'rejected',
            null => null,
        };
    }
}
