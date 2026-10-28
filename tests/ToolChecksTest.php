<?php

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\CancelOrderTool;
use Spatie\LaravelAiTests\Tests\TestSupport\LookupOrderMcpTool;
use Spatie\LaravelAiTests\Tests\TestSupport\LookupOrderTool;
use Spatie\LaravelAiTests\Tests\TestSupport\RefundOrderTool;
use Spatie\LaravelAiTests\Tests\TestSupport\ScriptedDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\ShippingApiTool;
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

    expect(checkSummaries($turn))->toBe([
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

it('records whether each tool call completed, failed, was rejected or got no result', function () {
    $response = new AgentResponse('invocation-1', 'I could only look up one order.', new TextUsage, new Meta);

    $response->withToolCallsAndResults(
        collect([
            new ToolCall('call-1', 'lookup_order', ['order' => '1']),
            new ToolCall('call-2', 'lookup_order', ['order' => '2']),
            new ToolCall('call-3', 'cancel_order', ['order' => '3']),
            new ToolCall('call-4', 'cancel_order', ['order' => '4']),
        ]),
        collect([
            new ToolResult('call-1', 'lookup_order', ['order' => '1'], 'Shipped.'),
            new ToolResult('call-2', 'lookup_order', ['order' => '2'], 'The tool call failed: timeout', failed: true),
            new ToolResult('call-3', 'cancel_order', ['order' => '3'], 'The user rejected this tool call.', denied: true),
        ]),
    );

    $aiTest = makeAiTest('looks up several orders');

    $aiTest
        ->driver(new ScriptedDriver([$response]))
        ->runs(1)
        ->user('Look up orders 1 and 2, and cancel 3 and 4.');

    $aiTest->execute();

    expect(array_column(resultFile('looks-up-several-orders')->get('turns')[0]['runs'][0]['tool_calls'], 'status'))
        ->toBe(['completed', 'failed', 'rejected', 'no_result']);
});

it('resolves a tool class without a public name method to its class name', function (string $tool, string $name) {
    $response = new AgentResponse('invocation-1', 'Done.', new TextUsage, new Meta);

    $response->withToolCallsAndResults(
        collect([new ToolCall('call-1', $name, ['order' => '1234'])]),
        collect([new ToolResult('call-1', $name, ['order' => '1234'], 'Done.')]),
    );

    $aiTest = makeAiTest('calls the tool');

    $aiTest
        ->driver(new ScriptedDriver([$response]))
        ->runs(1)
        ->user('Handle order 1234.')
        ->expectTools(called: [$tool]);

    $aiTest->execute();

    expect(resultFile('calls-the-tool')->get('turns')[0]['checks'][0])
        ->label->toBe("Calls `{$name}`")
        ->passed_runs->toBe(1);
})->with([
    'without a name method' => [CancelOrderTool::class, 'CancelOrderTool'],
    'with a protected name method' => [RefundOrderTool::class, 'RefundOrderTool'],
]);

it('fails before the first run when a tool name cannot be resolved', function () {
    $aiTest = makeAiTest('tracks the shipment');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is my parcel?')
        ->expectTools(called: [ShippingApiTool::class]);

    expect(fn () => $aiTest->execute())->toThrow(
        LogicException::class,
        'The AI test "tracks the shipment" has a check that can\'t be set up. Could not create the tool '.ShippingApiTool::class.' to read its name',
    );

    SupportAgent::assertNeverPrompted();
});
