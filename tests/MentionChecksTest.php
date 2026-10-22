<?php

use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

it('checks that the answer mentions each value, in order when asked', function () {
    SupportAgent::fake(['Order A-2 shipped. Order A-1 is late, track it at https://shop.example.test/customers/1/orders/A-1/shipments/tracking']);

    $aiTest = makeAiTest('mentions the orders');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Where are my orders?')
        ->expectMentions(
            fn (ShopDriver $driver) => "A-{$driver->customer->id}",
            fn (ShopDriver $driver) => "https://shop.example.test/customers/{$driver->customer->id}/orders/A-1/shipments/tracking",
            'A-9',
        )
        ->expectMentionsInOrder('A-2', 'A-1')
        ->expectMentionsInOrder('A-1', 'A-2');

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class);

    $turn = resultFile('mentions-the-orders')->get('turns')[0];

    expect(array_map(fn (array $check) => [$check['label'], $check['kind'], $check['passed_runs']], $turn['checks']))->toBe([
        ['Mentions `A-1`', 'mentions', 1],
        ['Mentions `https://shop.example.test…rs/1/orders/A-1/shipments/tracking`', 'mentions', 1],
        ['Mentions `A-9`', 'mentions', 0],
        ['Mentions `A-2`, then `A-1`', 'mentions', 1],
        ['Mentions `A-1`, then `A-2`', 'mentions', 0],
    ]);

    expect($turn['runs'][0]['verdicts'])->toBe([
        ['check' => 'Mentions `A-9`', 'passed' => false, 'reason' => 'Does not mention A-9.'],
        ['check' => 'Mentions `A-1`, then `A-2`', 'passed' => false, 'reason' => 'Mentions A-2 before A-1.'],
    ]);
});
