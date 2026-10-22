<?php

namespace Spatie\LaravelAiTests\Transcript;

class ToolCallRecord
{
    public ?string $result = null;

    public ToolCallStatus $status = ToolCallStatus::Completed;

    public ?bool $approved = null;

    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments,
    ) {}

    public function argumentList(): string
    {
        return collect($this->arguments)
            ->map(fn (mixed $value, string $key): string => "{$key}: ".json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            ->implode(', ');
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
