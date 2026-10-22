<?php

namespace Spatie\LaravelAiTests\Checks;

use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

abstract class Check
{
    abstract public function label(): string;

    abstract public function evaluate(Transcript $transcript): CheckOutcome;
}
