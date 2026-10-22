<?php

namespace Spatie\LaravelAiTests\Drivers;

interface DescribesContext
{
    /** @return array<string, string> */
    public function context(): array;
}
