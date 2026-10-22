<?php

namespace Spatie\LaravelAiTests\Viewer;

class CheckRow
{
    /** @param array<int, bool> $runs */
    public function __construct(
        public string $label,
        public array $runs,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): self
    {
        $runs = is_array($data['runs'] ?? null) ? $data['runs'] : [];

        return new self(
            label: (string) ($data['label'] ?? ''),
            runs: array_map(fn (mixed $passed): bool => $passed === true, $runs),
        );
    }

    public function labelHtml(): string
    {
        return Html::withCode($this->label);
    }

    public function passedRuns(): int
    {
        return count(array_filter($this->runs));
    }

    public function runCount(): int
    {
        return count($this->runs);
    }

    public function status(): string
    {
        return match (true) {
            $this->runs === [] => 'unknown',
            $this->passedRuns() === $this->runCount() => 'passed',
            $this->passedRuns() === 0 => 'failed',
            default => 'partial',
        };
    }
}
