<?php

use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Results\ResultSlugs;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

aiTest('registers an ai test as a pest test')
    ->driver(function () {
        SupportAgent::fake(['Hello.']);

        return new AgentDriver(SupportAgent::class);
    })
    ->runs(1)
    ->user('Hi')
    ->expectMaxWords(1);

it('refuses two ai tests that write to the same result file', function () {
    ResultSlugs::claim('looks-up-an-order', 'tests/OrderTest.php:10');
    ResultSlugs::claim('looks-up-an-order', 'tests/OrderTest.php:10');

    expect(fn () => ResultSlugs::claim('looks-up-an-order', 'tests/OtherOrderTest.php:20'))->toThrow(
        LogicException::class,
        'The AI tests at tests/OrderTest.php:10 and tests/OtherOrderTest.php:20 would both write their result to looks-up-an-order.yaml.',
    );
});
