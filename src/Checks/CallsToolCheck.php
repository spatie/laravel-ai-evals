<?php

namespace Spatie\LaravelAiEvals\Checks;

use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;

class CallsToolCheck extends ToolCheck
{
    public function label(): string
    {
        return "Calls {$this->tool()}";
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        return $this->outcome($this->calls($transcript), "Did not call {$this->tool()}.");
    }
}
