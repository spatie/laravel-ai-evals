<?php

namespace Spatie\LaravelAiTests\Checks;

interface Expectation
{
    public function label(): string;

    public function kind(): ExpectationKind;
}
