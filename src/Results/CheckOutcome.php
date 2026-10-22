<?php

namespace Spatie\LaravelAiTests\Results;

class CheckOutcome
{
    public function __construct(
        public string $label,
        public bool $passed,
        public ?string $reason = null,
        public bool $judged = false,
    ) {}
}
