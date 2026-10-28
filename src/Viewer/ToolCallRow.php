<?php

namespace Spatie\LaravelAiTests\Viewer;

use Spatie\LaravelAiTests\Transcript\ToolCallStatus;

class ToolCallRow
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $name,
        public array $arguments,
        public ?ToolCallStatus $status,
        public ?string $approval,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): self
    {
        return new self(
            name: Value::string($data['name'] ?? null) ?? '',
            arguments: Value::array($data['arguments'] ?? null),
            status: ToolCallStatus::tryFrom(Value::string($data['status'] ?? null) ?? ''),
            approval: Value::string($data['approval'] ?? null),
        );
    }

    public function argumentList(): string
    {
        return collect($this->arguments)
            ->map(fn (mixed $value, int|string $key): string => "{$key}: ".json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->implode(', ');
    }

    /** @return array<int, string> */
    public function details(): array
    {
        $showsStatus = $this->status !== null
            && $this->status !== ToolCallStatus::Completed
            && $this->status->value !== $this->approval;

        return array_values(array_filter([
            $this->approval,
            $showsStatus ? str_replace('_', ' ', $this->status->value) : null,
        ]));
    }
}
