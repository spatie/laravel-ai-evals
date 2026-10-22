<?php

use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Judge\JudgeAgent;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopSeeder;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

it('matches verdicts to criteria by number', function () {
    SupportAgent::fake(['Order 1234 shipped.']);

    JudgeAgent::fake([
        ['verdicts' => [
            ['criterion' => 2, 'reason' => 'It is rude.', 'pass' => false],
            ['criterion' => 1, 'reason' => 'It names the order.', 'pass' => true],
        ]],
    ]);

    $aiTest = makeAiTest('names the order');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is my order?')
        ->expectRubric('Names the order.', 'Is polite.', 'Gives a date.');

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class);

    expect(resultFile('names-the-order')->get('turns')[0]['runs'][0]['verdicts'])->toBe([
        ['check' => 'Names the order.', 'passed' => true, 'reason' => 'It names the order.'],
        ['check' => 'Is polite.', 'passed' => false, 'reason' => 'It is rude.'],
        ['check' => 'Gives a date.', 'passed' => false, 'reason' => 'The judge returned no verdict.'],
    ]);

    JudgeAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, "Assistant answer:\nOrder 1234 shipped.")
        && str_contains($prompt->prompt, "1. Names the order.\n2. Is polite.\n3. Gives a date."));
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
        ->seed(ShopSeeder::class)
        ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->user))
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
