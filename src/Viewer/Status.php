<?php

namespace Spatie\LaravelAiTests\Viewer;

enum Status: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Partial = 'partial';
    case Unjudged = 'unjudged';
    case Unknown = 'unknown';

    public static function fromCounts(mixed $passed, mixed $runs): self
    {
        if (! is_numeric($passed) || ! is_numeric($runs) || (int) $runs === 0) {
            return self::Unknown;
        }

        return match ((int) $passed) {
            (int) $runs => self::Passed,
            0 => self::Failed,
            default => self::Partial,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
