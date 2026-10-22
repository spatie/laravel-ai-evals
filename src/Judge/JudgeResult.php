<?php

namespace Spatie\LaravelAiTests\Judge;

use Spatie\LaravelAiTests\Results\CheckOutcome;

class JudgeResult
{
    /** @param array<int, CheckOutcome> $outcomes */
    public function __construct(
        public array $outcomes,
        public ?string $model = null,
    ) {}
}
