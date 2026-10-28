<?php

use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Judge\JudgeAgent;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

it('matches verdicts to criteria by number', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => '1234']),
        'Order 1234 shipped.',
    ]);

    JudgeAgent::fake([
        ['verdicts' => [
            ['criterion' => 2, 'reason' => 'It is rude.', 'pass' => false],
            ['criterion' => 1, 'reason' => 'It names the order.', 'pass' => true],
        ]],
    ]);

    $aiTest = makeAiTest('names the order');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Where is my order?')
        ->expectRubric('Names the order.', 'Is polite.');

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class);

    expect(resultFile('names-the-order')->get('turns')[0]['runs'][0]['verdicts'])->toBe([
        ['check' => 'Names the order.', 'passed' => true, 'reason' => 'It names the order.'],
        ['check' => 'Is polite.', 'passed' => false, 'reason' => 'It is rude.'],
    ]);

    JudgeAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, "Tool call `lookup_order` {\"order\":\"1234\"} (completed)\nResult: Order 1234 shipped yesterday.")
        && str_contains($prompt->prompt, "Assistant answer:\nOrder 1234 shipped.")
        && str_contains($prompt->prompt, "1. Names the order.\n2. Is polite."));
});

it('treats a missing verdict as a judge error, and still fails a run that failed a check', function () {
    SupportAgent::fake(['Order 1234 shipped yesterday, as planned.', 'Order 1234 shipped.']);

    JudgeAgent::fake(fn () => ['verdicts' => [['criterion' => 2, 'reason' => 'It is polite.', 'pass' => true]]]);

    $aiTest = makeAiTest('skips a verdict');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(2)
        ->user('Where is my order?')
        ->expectMaxWords(3)
        ->expectRubric('Names the order.', 'Is polite.');

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class, '1 unjudged run left out.');

    $result = resultFile('skips-a-verdict');

    expect($result->data)->toMatchArray(['passed_runs' => 0, 'unjudged_runs' => 1]);

    expect($result->get('turns')[0]['runs'][0]['judge_error'])
        ->toBe('LogicException: The judge must return one verdict for each criterion from 1 to 2, but returned verdicts for criteria [2].');
});

it('gives the judge the earlier turns as context without grading them', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => '1234']),
        'Order 1234 shipped yesterday.',
        'It arrives tomorrow.',
    ]);

    JudgeAgent::fake([
        ['verdicts' => [['criterion' => 1, 'reason' => 'It gives a date.', 'pass' => true]]],
    ]);

    $aiTest = makeAiTest('answers a follow-up question');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Where is order 1234?')
        ->user('When does it arrive?')
        ->expectRubric('Gives a delivery date.');

    $aiTest->execute();

    JudgeAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, implode("\n", [
        '# Earlier turns',
        '',
        'Context only. Do not grade these turns.',
        '',
        '## Turn 1',
        '',
        'User: Where is order 1234?',
        '',
        'Tool call `lookup_order` {"order":"1234"} (completed)',
        '',
        'Assistant answer:',
        'Order 1234 shipped yesterday.',
        '',
        '# Turn to grade',
        '',
        'User: When does it arrive?',
        '',
        'Assistant answer:',
        'It arrives tomorrow.',
    ])));
});
