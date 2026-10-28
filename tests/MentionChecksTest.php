<?php

use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

it('checks that the answer mentions each value, in order when asked, ignoring case', function () {
    SupportAgent::fake(['Order A-2 shipped. Order A-1 is late, track it at https://shop.example.test/customers/1/orders/A-1/shipments/tracking']);

    $aiTest = makeAiTest('mentions the orders');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Where are my orders?')
        ->expectMentions(
            fn (ShopDriver $driver) => "a-{$driver->customer->id}",
            fn (ShopDriver $driver) => "https://shop.example.test/customers/{$driver->customer->id}/orders/A-1/shipments/tracking",
            'A-9',
        )
        ->expectMentionsInOrder('order a-2', 'ORDER A-1')
        ->expectMentionsInOrder('A-1', 'A-2')
        ->expectMentionsInOrder('A-1', 'order')
        ->expectMentionsInOrder('A-2', 'A-9');

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class);

    $turn = resultFile('mentions-the-orders')->get('turns')[0];

    expect(checkSummaries($turn))->toBe([
        ['Mentions `a-1`', 'mentions', 1],
        ['Mentions `https://shop.example.test…rs/1/orders/A-1/shipments/tracking`', 'mentions', 1],
        ['Mentions `A-9`', 'mentions', 0],
        ['Mentions `order a-2`, then `ORDER A-1`', 'mentions', 1],
        ['Mentions `A-1`, then `A-2`', 'mentions', 0],
        ['Mentions `A-1`, then `order`', 'mentions', 1],
        ['Mentions `A-2`, then `A-9`', 'mentions', 0],
    ]);

    expect($turn['runs'][0]['verdicts'])->toBe([
        ['check' => 'Mentions `A-9`', 'passed' => false, 'reason' => 'Does not mention `A-9`.'],
        ['check' => 'Mentions `A-1`, then `A-2`', 'passed' => false, 'reason' => 'Mentions `A-2` before `A-1`.'],
        ['check' => 'Mentions `A-2`, then `A-9`', 'passed' => false, 'reason' => 'Does not mention `A-9`.'],
    ]);
});

it('checks that the answer mentions none of the values, ignoring case', function () {
    SupportAgent::fake(['Two Shipment groups need attention: A-1 and A-2.']);

    $aiTest = makeAiTest('avoids old terminology');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Which orders need attention?')
        ->expectNotMentions(
            'shipment group',
            fn (ShopDriver $driver) => "B-{$driver->customer->id}",
        );

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class);

    $turn = resultFile('avoids-old-terminology')->get('turns')[0];

    expect(checkSummaries($turn))->toBe([
        ['Does not mention `shipment group`', 'mentions', 0],
        ['Does not mention `B-1`', 'mentions', 1],
    ]);

    expect($turn['runs'][0]['verdicts'])->toBe([
        ['check' => 'Does not mention `shipment group`', 'passed' => false, 'reason' => 'Mentions `Shipment group`.'],
    ]);
});
