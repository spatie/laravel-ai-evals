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
        $runs = Value::array($data['runs'] ?? null);

        return new self(
            label: Value::string($data['label'] ?? null) ?? '',
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

    public function status(): Status
    {
        return Status::fromCounts($this->passedRuns(), $this->runCount());
    }
}
