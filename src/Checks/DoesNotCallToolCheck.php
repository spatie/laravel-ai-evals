<?php

namespace Spatie\LaravelAiTests\Checks;

use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\ToolName;
use Spatie\LaravelAiTests\Transcript\Transcript;

class DoesNotCallToolCheck extends Check
{
    protected ?string $toolName = null;

    public function __construct(
        protected string $tool,
    ) {}

    public function label(): string
    {
        return 'Does not call `'.$this->toolName().'`';
    }

    public function kind(): string
    {
        return 'tool';
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $toolName = $this->toolName();

        $called = in_array($toolName, $transcript->toolNames(), true);

        return new CheckOutcome(
            expectation: $this,
            passed: ! $called,
            reason: $called ? "Called {$toolName}." : null,
        );
    }

    protected function toolName(): string
    {
        return $this->toolName ??= ToolName::of($this->tool);
    }
}
