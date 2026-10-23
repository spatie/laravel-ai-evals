<?php

namespace Spatie\LaravelAiTests\Judge;

class JudgeOutput
{
    /** @param array<int, array{criterion: int, reason: string, pass: bool}> $verdicts */
    public function __construct(
        public array $verdicts,
        public ?string $model = null,
    ) {}
}
