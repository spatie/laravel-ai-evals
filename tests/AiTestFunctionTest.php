<?php

use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

aiTest('registers an ai test as a pest test')
    ->driver(function () {
        SupportAgent::fake(['Hello.']);

        return new AgentDriver(SupportAgent::class);
    })
    ->runs(1)
    ->user('Hi')
    ->expectMaxWords(1);
