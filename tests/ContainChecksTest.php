<?php

use Spatie\LaravelAiEvals\Evaluation;
use Spatie\LaravelAiEvals\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiEvals\Tests\TestSupport\SupportAgent;

it('checks that the answer contains each value, in order when asked, ignoring case', function () {
    SupportAgent::fake(['Order A-2 shipped. Order A-1 is late, track it at https://shop.example.test/customers/1/orders/A-1/shipments/tracking']);

    $evaluation = new Evaluation('mentions the orders');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Where are my orders?')
        ->toContain(
            fn (ShopDriver $driver) => "a-{$driver->customer->id}",
            fn (ShopDriver $driver) => "https://shop.example.test/customers/{$driver->customer->id}/orders/A-1/shipments/tracking",
            'A-9',
        )
        ->toContainInOrder('order a-2', 'ORDER A-1')
        ->toContainInOrder('A-1', 'A-2')
        ->toContainInOrder('A-1', 'order')
        ->toContainInOrder('A-2', 'A-9');

    expect(outcomes(runEvaluation($evaluation)))->toBe([
        ['Contains `a-1`', true, null],
        ['Contains `https://shop.example.test…rs/1/orders/A-1/shipments/tracking`', true, null],
        ['Contains `A-9`', false, 'Does not contain `A-9`.'],
        ['Contains `order a-2`, then `ORDER A-1`', true, null],
        ['Contains `A-1`, then `A-2`', false, 'Contains `A-2` before `A-1`.'],
        ['Contains `A-1`, then `order`', true, null],
        ['Contains `A-2`, then `A-9`', false, 'Does not contain `A-9`.'],
    ]);
});

it('checks that the answer contains none of the values with not, ignoring case', function () {
    SupportAgent::fake(['Two Shipment groups need attention: A-1 and A-2.']);

    $evaluation = new Evaluation('avoids old terminology');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Which orders need attention?')
        ->not->toContain('shipment group', fn (ShopDriver $driver) => "B-{$driver->customer->id}")
        ->toContain('A-1');

    expect(outcomes(runEvaluation($evaluation)))->toBe([
        ['Does not contain `shipment group`', false, 'Contains `Shipment group`.'],
        ['Does not contain `B-1`', true, null],
        ['Contains `A-1`', true, null],
    ]);
});

it('refuses not before an expectation that has no opposite', function (Closure $expectation, string $message) {
    $turn = (new Evaluation('negates a word limit'))->prompt('Hi');

    expect(fn () => $expectation($turn))->toThrow(LogicException::class, $message);
})->with([
    'a word limit' => [fn ($turn) => $turn->not->toHaveMaxWords(10), "->not can't be used with toHaveMaxWords(). It only works with toContain() and toHaveToolCalls()."],
    'another not' => [fn ($turn) => $turn->not->not, '->not is already negating the next expectation.'],
]);

it('refuses a not at the end of an evaluation', function () {
    $evaluation = new Evaluation('ends with not');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Hi')
        ->not;

    expect(fn () => $evaluation->execute())->toThrow(LogicException::class, '->not must be followed by toContain() or toHaveToolCalls().');
});
