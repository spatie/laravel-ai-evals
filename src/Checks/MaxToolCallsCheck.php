<?php

namespace Spatie\LaravelAiTests\Checks;

use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

class MaxToolCallsCheck extends Check
{
    public function __construct(
        protected int $maxCalls,
    ) {}

    public function label(): string
    {
        return "At most {$this->maxCalls} tool calls";
    }

    public function kind(): ExpectationKind
    {
        return ExpectationKind::Tool;
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $callCount = count($transcript->toolCalls);

        $passed = $callCount <= $this->maxCalls;

        return new CheckOutcome(
            expectation: $this,
            passed: $passed,
            reason: $passed ? null : "Made {$callCount} tool calls.",
        );
    }
}
