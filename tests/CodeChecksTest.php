<?php

use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;
use Spatie\LaravelAiTests\Transcript\ToolCallRecord;
use Spatie\LaravelAiTests\Transcript\Transcript;

it('checks a turn with code against the transcript and the data of the run', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => '1234']),
        'Order 1234 shipped yesterday.',
        'You are welcome.',
    ]);

    $aiTest = makeAiTest('checks the order lookup with code');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Where is order 1234?')
        ->expectThat('Looks up order 1234', fn (Transcript $transcript) => array_map(
            fn (ToolCallRecord $toolCall) => [$toolCall->arguments, $toolCall->result],
            array_values($transcript->toolCalls),
        ) === [[['order' => '1234'], 'Order 1234 shipped yesterday.']])
        ->expectThat('Stores the conversation of the customer', fn ($driver) => DB::table('agent_conversations')
            ->where('participant_id', $driver->customer->id)
            ->exists())
        ->expectThat('Offers a tracking link', fn ($transcript) => str_contains($transcript->answer(), 'tracking') ?: 'Does not offer a tracking link.')
        ->expectThat('Answers in Dutch', fn () => false)
        ->expectThat('Queries a missing table', fn () => DB::table('missing_table')->count() === 0)
        ->user('Thanks!')
        ->expectThat('Still reaches the database', fn () => DB::table('agent_conversations')->count() === 1);

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class, '0/1 runs passed');

    $turns = resultFile('checks-the-order-lookup-with-code')->get('turns');

    expect(array_map(fn (array $check) => [$check['label'], $check['kind'], $check['passed_runs']], $turns[0]['checks']))->toBe([
        ['Looks up order 1234', 'code', 1],
        ['Stores the conversation of the customer', 'code', 1],
        ['Offers a tracking link', 'code', 0],
        ['Answers in Dutch', 'code', 0],
        ['Queries a missing table', 'code', 0],
    ]);

    expect($turns[0]['runs'][0]['verdicts'])
        ->toHaveCount(3)
        ->sequence(
            fn ($verdict) => $verdict->toBe(['check' => 'Offers a tracking link', 'passed' => false, 'reason' => 'Does not offer a tracking link.']),
            fn ($verdict) => $verdict->toBe(['check' => 'Answers in Dutch', 'passed' => false]),
            fn ($verdict) => $verdict->reason->toContain('no such table: missing_table'),
        );

    expect($turns[1]['checks'][0]['passed_runs'])->toBe(1);
});
