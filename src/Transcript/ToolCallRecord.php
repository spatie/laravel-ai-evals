<?php

namespace Spatie\LaravelAiTests\Transcript;

class ToolCallRecord
{
    public ?string $result = null;

    public ToolCallStatus $status = ToolCallStatus::Completed;

    public ?bool $approved = null;

    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $name,
        public array $arguments,
    ) {}

    public function decisionLabel(): ?string
    {
        return match ($this->approved) {
            true => 'approved',
            false => 'rejected',
            null => null,
        };
    }
}
