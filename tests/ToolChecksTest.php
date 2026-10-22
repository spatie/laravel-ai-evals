<?php

use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\LookupOrderMcpTool;
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

    $turn = resultFile('looks-up-the-order')->get('turns')[0];

    expect(array_map(fn (array $check) => [$check['label'], $check['kind'], $check['passed_runs']], $turn['checks']))->toBe([
        ['Calls `lookup_order`', 'tool', 1],
        ['Does not call `cancel_order`', 'tool', 1],
        ['At most 1 tool calls', 'tool', 1],
    ]);

    expect($turn['runs'][0]['tool_calls'])->toBe([
        ['name' => 'lookup_order', 'arguments' => ['order' => '1234'], 'status' => 'completed'],
    ]);
});

it('fails a run that calls a forbidden tool', function () {
    $aiTest = makeAiTest('does not look up orders');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is order 1234?')
        ->expectTools(notCalled: [LookupOrderTool::class], maxCalls: 0);

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class);

    expect(resultFile('does-not-look-up-orders')->get('turns')[0]['runs'][0]['verdicts'])->toBe([
        ['check' => 'Does not call `lookup_order`', 'passed' => false, 'reason' => 'Called lookup_order.'],
        ['check' => 'At most 0 tool calls', 'passed' => false, 'reason' => 'Made 1 tool calls.'],
    ]);
});

it('resolves the name of a tool class that is not a laravel/ai tool', function () {
    $aiTest = makeAiTest('looks up the order over mcp');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is order 1234?')
        ->expectTools(called: [LookupOrderMcpTool::class]);

    $aiTest->execute();

    expect(resultFile('looks-up-the-order-over-mcp')->get('turns')[0]['checks'][0])
        ->label->toBe('Calls `lookup_order`')
        ->passed_runs->toBe(1);
});
