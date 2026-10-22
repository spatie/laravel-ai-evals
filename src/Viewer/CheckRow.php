<?php

namespace Spatie\LaravelAiTests\Viewer;

class CheckRow
{
    public function __construct(
        public string $label,
        public string $labelHtml,
        public ?int $passed,
        public ?int $runs,
    ) {}

    public function status(): string
    {
        if ($this->passed === null || $this->runs === null) {
            return 'unknown';
        }

        if ($this->passed === $this->runs) {
            return 'passed';
        }

        return $this->passed === 0 ? 'failed' : 'partial';
    }
}
