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

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        $callCount = count($transcript->toolCalls);

        return new CheckOutcome(
            label: $this->label(),
            passed: $callCount <= $this->maxCalls,
            reason: $callCount <= $this->maxCalls ? null : "Made {$callCount} tool calls.",
        );
    }
}
