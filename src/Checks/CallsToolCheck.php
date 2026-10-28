<?php

namespace Spatie\LaravelAiTests\Checks;

use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\ToolName;
use Spatie\LaravelAiTests\Transcript\Transcript;

class CallsToolCheck extends Check
{
    protected string $toolName;

    public function __construct(string $tool)
    {
        $this->toolName = ToolName::of($tool);
    }

    public function label(): string
    {
        return "Calls `{$this->toolName}`";
    }

    public function kind(): ExpectationKind
    {
        return ExpectationKind::Tool;
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $called = $transcript->callsTo($this->toolName) !== [];

        return new CheckOutcome(
            expectation: $this,
            passed: $called,
            reason: $called ? null : "Did not call {$this->toolName}.",
        );
    }
}
