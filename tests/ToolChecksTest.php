<?php

use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\LookupOrderTool;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

beforeEach(function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => '1234']),
        'Order 1234 shipped yesterday.',
    ]);
});

it('checks which tools the agent called', function () {
    $aiTest = makeAiTest('looks up the order');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is order 1234?')
        ->expectTools(called: [LookupOrderTool::class], notCalled: ['cancel_order'], maxCalls: 1);

    $aiTest->execute();

    expect(resultFile('looks-up-the-order')->body)
        ->toContain('| Calls `lookup_order` | 1/1 |')
        ->toContain('| Does not call `cancel_order` | 1/1 |')
        ->toContain('| At most 1 tool calls | 1/1 |')
        ->toContain('Tools: `lookup_order` (order: "1234")');
});

it('fails a run that calls a forbidden tool', function () {
    $aiTest = makeAiTest('does not look up orders');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is order 1234?')
        ->expectTools(notCalled: [LookupOrderTool::class], maxCalls: 0);

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class);

    expect(resultFile('does-not-look-up-orders')->body)
        ->toContain('| Does not call `lookup_order` | 0/1 |')
        ->toContain('| At most 0 tool calls | 0/1 |');
});
