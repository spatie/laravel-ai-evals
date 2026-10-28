<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\SkippedWithMessageException;
use Spatie\LaravelAiEvals\AiEvals;
use Spatie\LaravelAiEvals\Drivers\AgentDriver;
use Spatie\LaravelAiEvals\Evaluation;
use Spatie\LaravelAiEvals\Judge\JudgeAgent;
use Spatie\LaravelAiEvals\Plugin;
use Spatie\LaravelAiEvals\Tests\TestSupport\LookupOrderTool;
use Spatie\LaravelAiEvals\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiEvals\Tests\TestSupport\SupportAgent;

evaluate('registers an evaluation as a pest test')
    ->agent(function () {
        SupportAgent::fake(['Hello.']);

        return new AgentDriver(SupportAgent::class);
    })
    ->prompt('Hi')
    ->toHaveMaxWords(1);

it('skips the evaluation without eval mode', function () {
    Plugin::resetEvalMode();

    $evaluation = new Evaluation('needs a real model');

    $evaluation
        ->agent(fn () => throw new RuntimeException('The agent should not be set up.'))
        ->prompt('Where is my order?');

    expect(fn () => $evaluation->execute())->toThrow(
        SkippedWithMessageException::class,
        'Eval skipped. Run with [--evals] to evaluate against a real model.',
    );
});

it('enables eval mode with the evals flag and removes the flag from the arguments', function () {
    Plugin::resetEvalMode();

    expect((new Plugin)->handleArguments(['vendor/bin/pest', '--filter=orders']))->toBe(['vendor/bin/pest', '--filter=orders'])
        ->and(Plugin::isEvalMode())->toBeFalse()
        ->and((new Plugin)->handleArguments(['vendor/bin/pest', '--evals', '--filter=orders']))->toBe(['vendor/bin/pest', '--filter=orders'])
        ->and(Plugin::isEvalMode())->toBeTrue();
});

it('enables eval mode from the environment, as parallel workers do', function () {
    Plugin::resetEvalMode();

    putenv('PEST_EVALS=1');

    expect(Plugin::isEvalMode())->toBeTrue();
})->after(function () {
    putenv('PEST_EVALS');
});

it('fails with the transcript and failed checks of every failed run', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => '1234']),
        'Order 1234 shipped yesterday.',
        "Your order shipped yesterday\nafter a long delay in the warehouse.",
    ]);

    JudgeAgent::fake([
        ['verdicts' => [['criterion' => 1, 'reason' => 'It does not blame anyone.', 'pass' => true]]],
        ['verdicts' => [['criterion' => 1, 'reason' => 'It blames the warehouse.', 'pass' => false]]],
    ]);

    $evaluation = new Evaluation('answers briefly');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->repeat(2, minPassRate: 1)
        ->prompt('Where is order 1234?')
        ->toHaveToolCalls([LookupOrderTool::class])
        ->toHaveMaxWords(5)
        ->toSatisfy('Does not blame anyone.');

    expect(failureMessage($evaluation))->toBe(implode("\n", [
        '1/2 runs passed, below the minimum pass rate of 1.',
        '',
        'Run 2, turn 1 failed',
        '  Prompt:',
        '    Where is order 1234?',
        '  Tool calls: none',
        '  Answer:',
        '    Your order shipped yesterday',
        '    after a long delay in the warehouse.',
        '  Failed checks:',
        '    ✗ Calls `lookup_order`',
        '      Did not call `lookup_order`.',
        '    ✗ At most 5 words',
        '      Answered in 11 words.',
        '    ✗ Does not blame anyone.',
        '      It blames the warehouse.',
    ]));
});

it('fails with the crash and the transcript of the crashed turn, with long tool arguments truncated', function () {
    $arguments = ['order' => '1234', 'reason' => str_repeat('The customer changed their mind. ', 10)];

    SupportAgent::fake(fn () => AgentResponse::fakeWithPendingApprovals([new PendingApproval('call-1', 'cancel_order', $arguments)]));

    $evaluation = new Evaluation('keeps asking for approval');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Cancel order 1234.');

    expect(failureMessage($evaluation))->toBe(implode("\n", [
        '0/1 runs passed, below the minimum pass rate of 1.',
        '',
        'Run 1, turn 1 crashed: LogicException: The agent kept asking for approval after 5 rounds.',
        '  Prompt:',
        '    Cancel order 1234.',
        '  Tool calls:',
        '    - cancel_order '.Str::limit(json_encode($arguments), 200).' (pending, rejected)',
        '  Answer:',
        '    (no answer)',
    ]));
});

it('passes the driver to the prompt closures', function () {
    SupportAgent::fake(['Order A-1 shipped.']);

    $evaluation = new Evaluation('uses the driver data in prompts');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt(fn (ShopDriver $shop) => "I am {$shop->customer->name}. Where is order A-{$shop->customer->id}?");

    $evaluation->execute();

    SupportAgent::assertPrompted('I am Ada. Where is order A-1?');
});

it('resolves expectation closures with the driver once before the runs', function () {
    SupportAgent::fake([
        new ToolCall('call-1', 'lookup_order', ['order' => 'A-1']),
        'Hi Ada, order A-1 shipped.',
        new ToolCall('call-2', 'lookup_order', ['order' => 'A-1']),
        'Hi Ada, order A-1 shipped.',
    ]);

    JudgeAgent::fake(fn () => ['verdicts' => [['criterion' => 1, 'reason' => 'It greets Ada.', 'pass' => true]]]);

    $resolvedTimes = 0;

    $evaluation = new Evaluation('uses the driver data in expectations');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->repeat(2)
        ->prompt('Where is my order?')
        ->toHaveToolCalls(fn ($driver) => [LookupOrderTool::class => ['order' => "A-{$driver->customer->id}"]])
        ->toHaveMaxToolCalls(fn () => 1)
        ->toContain(function (ShopDriver $shop) use (&$resolvedTimes) {
            $resolvedTimes++;

            return "A-{$shop->customer->id}";
        })
        ->toSatisfy(fn (ShopDriver $shop) => "Greets {$shop->customer->name} by name.");

    $result = runEvaluation($evaluation);

    expect($resolvedTimes)->toBe(1)
        ->and($result->passedRuns())->toBe(2)
        ->and(array_column(outcomes($result, run: 2), 0))->toBe([
            'Calls `lookup_order` with {"order":"A-1"}',
            'At most 1 tool calls',
            'Contains `A-1`',
            'Greets Ada by name.',
        ]);

    JudgeAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, '1. Greets Ada by name.'));
});

it('fails before the first run when an expectation closure returns no usable value', function () {
    $evaluation = new Evaluation('expects a missing order');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Where is my order?')
        ->toContain(fn () => null);

    expect(fn () => $evaluation->execute())->toThrow(
        LogicException::class,
        'The evaluation "expects a missing order" has a check that can\'t be set up. A closure given to toContain() returned null.',
    );

    SupportAgent::assertNeverPrompted();
});

it('accepts an agent instance, an agent class or a closure that returns an agent', function (Closure $agent) {
    SupportAgent::fake(['Hello.', 'Hello again.']);

    $evaluation = new Evaluation('greets the customer');

    $evaluation
        ->agent($agent())
        ->repeat(2)
        ->prompt('Hi')
        ->toHaveMaxWords(2);

    $evaluation->execute();

    SupportAgent::assertPrompted('Hi');
})->with([
    'an instance' => [fn () => new SupportAgent],
    'a class' => [fn () => SupportAgent::class],
    'a closure that returns an instance' => [fn () => fn () => new SupportAgent],
]);

it('refuses an agent that is not a driver or an agent', function () {
    $evaluation = new Evaluation('has no real agent');

    $evaluation
        ->agent(fn () => 'not an agent')
        ->prompt('Hi');

    expect(fn () => $evaluation->execute())->toThrow(LogicException::class, 'but is string.');
});

it('rolls back the database after each run', function () {
    $conversationCounts = [];

    SupportAgent::fake(function () use (&$conversationCounts) {
        $conversationCounts[] = DB::table('agent_conversations')->count();

        return 'Hello again.';
    });

    $evaluation = new Evaluation('starts every run with a clean database');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->repeat(2)
        ->prompt('Hi')
        ->prompt('Hi again')
        ->prompt('Still there?');

    $evaluation->execute();

    expect($conversationCounts)->toBe([0, 1, 1, 0, 1, 1]);
});

it('crashes a follow-up turn when the agent does not remember the conversation', function () {
    SupportAgent::fake(['Hello.', 'Hello again.']);

    $evaluation = new Evaluation('forgets the first turn');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->prompt('Hi')
        ->prompt('Hi again');

    expect(failureMessage($evaluation))
        ->toContain('Run 1, turn 2 crashed: LogicException: The agent '.SupportAgent::class.' does not remember the conversation');
});

it('passes with the configured repeat and minimum pass rate when two of three runs pass', function () {
    AiEvals::repeat(3, minPassRate: 0.66);

    SupportAgent::fake(['Shipped.', 'Your order shipped yesterday afternoon.', 'Shipped.']);

    $evaluation = new Evaluation('passes two of three runs');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->prompt('Where is my order?')
        ->toHaveMaxWords(2);

    $result = runEvaluation($evaluation);

    expect($result->passedRuns())->toBe(2)
        ->and($result->passed())->toBeTrue();
});

it('runs once and requires that run to pass when nothing is configured', function () {
    SupportAgent::fake(['Your order shipped yesterday afternoon.', 'Shipped.']);

    $evaluation = new Evaluation('runs once by default');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->prompt('Where is my order?')
        ->toHaveMaxWords(2);

    expect(failureMessage($evaluation))->toStartWith('0/1 runs passed, below the minimum pass rate of 1.');
});

it('uses the configured minimum pass rate when an evaluation only sets the repeat', function () {
    AiEvals::repeat(1, minPassRate: 0.67);

    SupportAgent::fake(['Shipped.', 'Your order shipped yesterday afternoon.', 'Shipped.']);

    $evaluation = new Evaluation('just misses the minimum pass rate');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->repeat(3)
        ->prompt('Where is my order?')
        ->toHaveMaxWords(2);

    expect(failureMessage($evaluation))->toStartWith('2/3 runs passed, below the minimum pass rate of 0.67.');
});

it('forces the number of runs over the repeat of the evaluation', function () {
    $_SERVER['AI_EVALS_REPEAT'] = '1';

    SupportAgent::fake(['Shipped.', 'Shipped yesterday afternoon by courier.']);

    $evaluation = new Evaluation('runs once');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->repeat(3, minPassRate: 1)
        ->prompt('Where is my order?')
        ->toHaveMaxWords(2);

    expect(runEvaluation($evaluation)->runs)->toHaveCount(1);
})->after(function () {
    unset($_SERVER['AI_EVALS_REPEAT']);
});

it('refuses an invalid repeat or minimum pass rate', function (int $times, float $minPassRate, string $message) {
    $evaluation = new Evaluation('is misconfigured');

    $evaluation
        ->agent(new AgentDriver(SupportAgent::class))
        ->repeat($times, $minPassRate)
        ->prompt('Where is my order?');

    expect(fn () => $evaluation->execute())->toThrow(LogicException::class, $message);
})->with([
    'no runs' => [0, 0.5, 'needs to run at least once, but repeats 0 times'],
    'a pass rate above 1' => [3, 66, 'must be between 0 and 1, but is 66'],
    'a negative pass rate' => [3, -0.5, 'must be between 0 and 1, but is -0.5'],
]);
