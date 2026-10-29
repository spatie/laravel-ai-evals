<?php

use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Spatie\LaravelAiEvals\AiEvals;
use Spatie\LaravelAiEvals\Drivers\AgentDriver;
use Spatie\LaravelAiEvals\Evaluation;
use Spatie\LaravelAiEvals\Judge\JudgeAgent;
use Spatie\LaravelAiEvals\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiEvals\Tests\TestSupport\ShopJudgeAgent;
use Spatie\LaravelAiEvals\Tests\TestSupport\SupportAgent;

it('grades all criteria of a turn in one judge call and matches verdicts by number', function () {
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

    $evaluation = new Evaluation('names the order');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Where is my order?')
        ->toSatisfy('Names the order.')
        ->toSatisfy('Is polite.');

    expect(outcomes(runEvaluation($evaluation)))->toBe([
        ['Names the order.', true, 'It names the order.'],
        ['Is polite.', false, 'It is rude.'],
    ]);

    JudgeAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, "Tool call `lookup_order` {\"order\":\"1234\"} (completed)\nResult: Order 1234 shipped yesterday.")
        && str_contains($prompt->prompt, "Assistant answer:\nOrder 1234 shipped.")
        && str_contains($prompt->prompt, "1. Names the order.\n2. Is polite."));
});

it('does not count runs the judge could not grade', function () {
    SupportAgent::fake(['Order 1234 shipped.', 'Order 1234 shipped.', 'Order 1234 shipped yesterday, as planned.']);

    $judgeCalls = 0;

    JudgeAgent::fake(function () use (&$judgeCalls) {
        if (++$judgeCalls === 1) {
            throw new RuntimeException('The judge is rate limited.');
        }

        return ['verdicts' => [['criterion' => 1, 'reason' => 'It names the order.', 'pass' => true]]];
    });

    $evaluation = new Evaluation('survives a judge error');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->repeat(3)
        ->prompt('Where is my order?')
        ->toHaveMaxWords(3)
        ->toSatisfy('Names the order.');

    expect(failureMessage($evaluation))->toBe(implode("\n", [
        '1/2 runs passed. 1 unjudged run left out.',
        '',
        'Run 1, turn 1 could not be judged: RuntimeException: The judge is rate limited.',
        '  Prompt:',
        '    Where is my order?',
        '  Tool calls: none',
        '  Answer:',
        '    Order 1234 shipped.',
        '',
        'Run 3, turn 1 failed',
        '  Prompt:',
        '    Where is my order?',
        '  Tool calls: none',
        '  Answer:',
        '    Order 1234 shipped yesterday, as planned.',
        '  Failed checks:',
        '    ✗ At most 3 words',
        '      Answered in 6 words.',
    ]));
});

it('fails when no run could be judged', function (array $verdicts, string $judgeError) {
    SupportAgent::fake(['Your order shipped yesterday.']);

    JudgeAgent::fake(fn () => ['verdicts' => $verdicts]);

    $evaluation = new Evaluation('cannot be judged');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->prompt('Where is my order?')
        ->toSatisfy('Says when the order shipped.');

    expect(failureMessage($evaluation))->toBe(implode("\n", [
        'No run could be judged, because the judge failed in every run.',
        '',
        "Run 1, turn 1 could not be judged: {$judgeError}",
        '  Prompt:',
        '    Where is my order?',
        '  Tool calls: none',
        '  Answer:',
        '    Your order shipped yesterday.',
    ]));
})->with([
    'wrong criterion numbers' => [
        [['criterion' => 2, 'reason' => 'It is polite.', 'pass' => true]],
        'LogicException: The judge must return one verdict for each criterion from 1 to 1, but returned verdicts for criteria [2].',
    ],
    'a verdict without pass' => [
        [['criterion' => 1, 'reason' => 'It says when.']],
        'LogicException: The judge returned a malformed verdict at position 1. Each verdict needs an integer `criterion`, a boolean `pass` and a string `reason`, but got {"criterion":1,"reason":"It says when."}.',
    ],
]);

it('gives the judge the earlier turns as context without grading them', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => '1234']),
        'Order 1234 shipped yesterday.',
        'It arrives tomorrow.',
    ]);

    JudgeAgent::fake([
        ['verdicts' => [['criterion' => 1, 'reason' => 'It gives a date.', 'pass' => true]]],
    ]);

    $evaluation = new Evaluation('answers a follow-up question');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Where is order 1234?')
        ->prompt('When does it arrive?')
        ->toSatisfy('Gives a delivery date.');

    $evaluation->execute();

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

it('judges with the configured agent and the model from the environment', function () {
    $_SERVER['PEST_EVALS_LARAVEL_SCORING_MODEL'] = 'judge-model';

    AiEvals::judgeUsing(ShopJudgeAgent::class);

    SupportAgent::fake(['Order 1234 shipped yesterday.']);

    ShopJudgeAgent::fake([
        ['verdicts' => [['criterion' => 1, 'reason' => 'It says when.', 'pass' => true]]],
    ]);

    $evaluation = new Evaluation('is graded by the shop judge');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->prompt('Where is my order?')
        ->toSatisfy('Says when the order shipped.');

    $evaluation->execute();

    ShopJudgeAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->model === 'judge-model');
    JudgeAgent::assertNeverPrompted();
})->after(function () {
    unset($_SERVER['PEST_EVALS_LARAVEL_SCORING_MODEL']);
});

it('refuses a judge agent without structured output', function () {
    expect(fn () => AiEvals::judgeUsing(SupportAgent::class))->toThrow(LogicException::class, 'must implement');
});
