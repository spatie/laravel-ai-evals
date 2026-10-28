<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Responses\Data\ToolCall;
use Spatie\LaravelAiEvals\Evaluation;
use Spatie\LaravelAiEvals\Tests\TestSupport\LookupOrderTool;
use Spatie\LaravelAiEvals\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiEvals\Tests\TestSupport\SupportAgent;
use Spatie\LaravelAiEvals\Transcript\ToolCallRecord;
use Spatie\LaravelAiEvals\Transcript\Transcript;

it('checks a turn with code against the transcript and the data of the run', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => '1234']),
        'Order 1234 shipped yesterday.',
        'You are welcome.',
    ]);

    $evaluation = new Evaluation('checks the order lookup with code');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Where is order 1234?')
        ->toPassCheck('Looks up order 1234', fn (Transcript $transcript) => array_map(
            fn (ToolCallRecord $toolCall) => [$toolCall->arguments, $toolCall->result],
            array_values($transcript->callsTo(LookupOrderTool::class)),
        ) === [[['order' => '1234'], 'Order 1234 shipped yesterday.']])
        ->toPassCheck('Stores the conversation of the customer', fn ($driver) => DB::table('agent_conversations')
            ->where('participant_id', $driver->customer->id)
            ->exists())
        ->toPassCheck('Offers a tracking link', fn ($transcript) => str_contains($transcript->answer(), 'tracking') ?: 'Does not offer a tracking link.')
        ->toPassCheck('Answers in Dutch', fn () => false)
        ->toPassCheck('Queries a missing table', fn () => DB::table('missing_table')->count() === 0)
        ->prompt('Thanks!')
        ->toPassCheck('Still reaches the database', fn () => DB::table('agent_conversations')->count() === 1);

    $result = runEvaluation($evaluation);

    expect(outcomes($result))
        ->toHaveCount(5)
        ->sequence(
            fn ($outcome) => $outcome->toBe(['Looks up order 1234', true, null]),
            fn ($outcome) => $outcome->toBe(['Stores the conversation of the customer', true, null]),
            fn ($outcome) => $outcome->toBe(['Offers a tracking link', false, 'Does not offer a tracking link.']),
            fn ($outcome) => $outcome->toBe(['Answers in Dutch', false, null]),
            fn ($outcome) => $outcome->{2}->toStartWith(QueryException::class.': ')->toContain('no such table: missing_table'),
        );

    expect(outcomes($result, turn: 2))->toBe([['Still reaches the database', true, null]]);
});
