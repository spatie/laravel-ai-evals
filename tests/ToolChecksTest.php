<?php

use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Spatie\LaravelAiEvals\Drivers\AgentDriver;
use Spatie\LaravelAiEvals\Evaluation;
use Spatie\LaravelAiEvals\Tests\TestSupport\CancelOrderTool;
use Spatie\LaravelAiEvals\Tests\TestSupport\LookupOrderMcpTool;
use Spatie\LaravelAiEvals\Tests\TestSupport\LookupOrderTool;
use Spatie\LaravelAiEvals\Tests\TestSupport\RefundOrderTool;
use Spatie\LaravelAiEvals\Tests\TestSupport\ScriptedDriver;
use Spatie\LaravelAiEvals\Tests\TestSupport\ShippingApiTool;
use Spatie\LaravelAiEvals\Tests\TestSupport\SupportAgent;

it('checks which tools the agent called, with which arguments, and how often', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => '1234', 'include' => ['items' => true, 'shipments' => true]]),
        'Order 1234 shipped yesterday.',
    ]);

    $evaluation = new Evaluation('looks up the order');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->prompt('Where is order 1234?')
        ->toHaveToolCalls([LookupOrderTool::class])
        ->toHaveToolCalls(['lookup_order' => ['order' => '1234', 'include' => ['shipments' => true]]])
        ->toHaveToolCalls(['lookup_order' => ['order' => 1234]])
        ->toHaveToolCalls(['lookup_order' => fn (array $arguments) => $arguments['order'] === '1234'])
        ->not->toHaveToolCalls(['cancel_order', 'lookup_order' => ['order' => '9999']])
        ->not->toHaveToolCalls([LookupOrderMcpTool::class])
        ->toHaveMaxToolCalls(1)
        ->toHaveMaxToolCalls(0);

    expect(outcomes(runEvaluation($evaluation)))->toBe([
        ['Calls `lookup_order`', true, null],
        ['Calls `lookup_order` with {"order":"1234","include":{"shipments":true}}', true, null],
        ['Calls `lookup_order` with {"order":1234}', false, 'Did not call `lookup_order` with {"order":1234}.'],
        ['Calls `lookup_order` with matching arguments', true, null],
        ['Does not call `cancel_order`', true, null],
        ['Does not call `lookup_order` with {"order":"9999"}', true, null],
        ['Does not call `lookup_order`', false, 'Called `lookup_order`.'],
        ['At most 1 tool calls', true, null],
        ['At most 0 tool calls', false, 'Made 1 tool calls.'],
    ]);
});

it('treats an argument closure that throws as not matching the call', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => 'A-1']),
        'Order A-1 shipped yesterday.',
    ]);

    $evaluation = new Evaluation('looks up the order without the customer');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->prompt('Where is order A-1?')
        ->toHaveToolCalls(['lookup_order' => fn (array $arguments) => $arguments['customer'] === 'Ada'])
        ->not->toHaveToolCalls(['lookup_order' => fn (array $arguments) => $arguments['customer'] === 'Ada']);

    expect(outcomes(runEvaluation($evaluation)))->toBe([
        ['Calls `lookup_order` with matching arguments', false, 'Did not call `lookup_order` with matching arguments.'],
        ['Does not call `lookup_order` with matching arguments', true, null],
    ]);
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

    $evaluation = new Evaluation('looks up several orders');

    $evaluation
        ->agent(new ScriptedDriver([$response]))
        ->prompt('Look up orders 1 and 2, and cancel 3 and 4.')
        ->toHaveMaxToolCalls(0);

    expect(failureMessage($evaluation))->toContain(implode("\n", [
        '  Tool calls:',
        '    - lookup_order {"order":"1"} (completed)',
        '    - lookup_order {"order":"2"} (failed)',
        '    - cancel_order {"order":"3"} (rejected)',
        '    - cancel_order {"order":"4"} (no result)',
    ]));
});

it('resolves a tool class without a public name method to its class name', function (string $tool, string $name) {
    $response = new AgentResponse('invocation-1', 'Done.', new TextUsage, new Meta);

    $response->withToolCallsAndResults(
        collect([new ToolCall('call-1', $name, ['order' => '1234'])]),
        collect([new ToolResult('call-1', $name, ['order' => '1234'], 'Done.')]),
    );

    $evaluation = new Evaluation('calls the tool');

    $evaluation
        ->agent(new ScriptedDriver([$response]))
        ->prompt('Handle order 1234.')
        ->toHaveToolCalls([$tool]);

    expect(outcomes(runEvaluation($evaluation)))->toBe([["Calls `{$name}`", true, null]]);
})->with([
    'without a name method' => [CancelOrderTool::class, 'CancelOrderTool'],
    'with a protected name method' => [RefundOrderTool::class, 'RefundOrderTool'],
]);

it('fails before the first run when a tool name cannot be resolved', function () {
    $evaluation = new Evaluation('tracks the shipment');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->prompt('Where is my parcel?')
        ->toHaveToolCalls([ShippingApiTool::class]);

    expect(fn () => $evaluation->execute())->toThrow(
        LogicException::class,
        'The evaluation "tracks the shipment" has a check that can\'t be set up. Could not create the tool '.ShippingApiTool::class.' to read its name',
    );

    SupportAgent::assertNeverPrompted();
});
