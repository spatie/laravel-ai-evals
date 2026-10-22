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
            name: Value::string($data['name'] ?? null) ?? '',
            arguments: is_array($data['arguments'] ?? null) ? $data['arguments'] : [],
            status: Value::string($data['status'] ?? null),
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
        $showsStatus = $this->status !== null && ! in_array($this->status, ['completed', $this->approval], true);

        return array_values(array_filter([
            $this->approval,
            $showsStatus ? $this->status : null,
        ]));
    }
}
