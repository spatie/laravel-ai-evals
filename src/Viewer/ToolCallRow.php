<?php

namespace Spatie\LaravelAiTests\Viewer;

class ToolCallRow
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $name,
        public array $arguments,
        public ?string $status,
        public ?string $approval,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromData(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            arguments: is_array($data['arguments'] ?? null) ? $data['arguments'] : [],
            status: isset($data['status']) ? (string) $data['status'] : null,
            approval: isset($data['approval']) ? (string) $data['approval'] : null,
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
        return array_values(array_filter([
            $this->approval,
            $this->status !== null && ! in_array($this->status, ['completed', $this->approval], true) ? $this->status : null,
        ]));
    }
}
