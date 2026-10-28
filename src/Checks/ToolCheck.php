<?php

namespace Spatie\LaravelAiEvals\Checks;

use Closure;
use Spatie\LaravelAiEvals\ToolName;
use Spatie\LaravelAiEvals\Transcript\ToolCallRecord;
use Spatie\LaravelAiEvals\Transcript\Transcript;

abstract class ToolCheck extends Check
{
    protected string $toolName;

    /** @param array<string, mixed>|Closure(array<string, mixed>): mixed|null $arguments */
    public function __construct(
        string $tool,
        protected array|Closure|null $arguments = null,
    ) {
        $this->toolName = ToolName::of($tool);
    }

    protected function calls(Transcript $transcript): bool
    {
        return $transcript->callsTo($this->toolName, $this->arguments) !== [];
    }

    protected function tool(): string
    {
        return match (true) {
            $this->arguments === null => "`{$this->toolName}`",
            $this->arguments instanceof Closure => "`{$this->toolName}` with matching arguments",
            default => "`{$this->toolName}` with ".ToolCallRecord::encodeArguments($this->arguments, ToolCallRecord::ShortArgumentsLength),
        };
    }
}
