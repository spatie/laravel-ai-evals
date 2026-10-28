<?php

namespace Spatie\LaravelAiEvals\Checks;

use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;

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

        return $this->outcome($callCount <= $this->maxCalls, "Made {$callCount} tool calls.");
    }
}
