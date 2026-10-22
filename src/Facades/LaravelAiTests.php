<?php

namespace Spatie\LaravelAiTests\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Spatie\LaravelAiTests\LaravelAiTests
 */
class LaravelAiTests extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Spatie\LaravelAiTests\LaravelAiTests::class;
    }
}
