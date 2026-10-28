<?php

namespace Spatie\LaravelAiEvals\Checks;

use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\Transcript;

class DoesNotCallToolCheck extends ToolCheck
{
    public function label(): string
    {
        return "Does not call {$this->tool()}";
    }

    public function evaluate(Transcript $transcript): CheckOutcome
    {
        return $this->outcome(! $this->calls($transcript), "Called {$this->tool()}.");
    }
}
