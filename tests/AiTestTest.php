<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Judge\JudgeAgent;
use Spatie\LaravelAiTests\Tests\TestSupport\LookupOrderTool;
use Spatie\LaravelAiTests\Tests\TestSupport\ScopedShopDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

it('writes a result file for a passing test', function () {
    SupportAgent::fake(['Your order shipped yesterday.', 'It ships with a tracking code.']);

    JudgeAgent::fake([
        ['verdicts' => [['criterion' => 1, 'reason' => 'It says when.', 'pass' => true]]],
    ]);

    $aiTest = makeAiTest('answers a question about an order');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Where is my order?')
        ->expectMaxWords(10)
        ->expectRubric('Says when the order shipped.')
        ->user('Can I track it?');

    $aiTest->execute();

    $result = resultFile('answers-a-question-about-an-order');

    expect($result->data)
        ->toMatchArray([
            'slug' => 'answers-a-question-about-an-order',
            'title' => 'Answers a question about an order',
            'runs' => 1,
            'passed_runs' => 1,
            'pass_rate' => 1.0,
            'min_pass_rate' => 0.66,
            'passed' => true,
        ])
        ->toHaveKeys(['model', 'judge_model', 'test_file', 'ran_at'])
        ->not->toHaveKey('context');

    expect($result->get('turns'))->toBe([
        [
            'prompt' => 'Where is my order?',
            'checks' => [
                ['label' => 'At most 10 words', 'kind' => 'max_words', 'passed_runs' => 1, 'runs' => [1 => true]],
                ['label' => 'Says when the order shipped.', 'kind' => 'rubric', 'passed_runs' => 1, 'runs' => [1 => true]],
            ],
            'runs' => [
                [
                    'run' => 1,
                    'passed' => true,
                    'tool_calls' => [],
                    'answer' => 'Your order shipped yesterday.',
                    'verdicts' => [
                        ['check' => 'Says when the order shipped.', 'passed' => true, 'reason' => 'It says when.'],
                    ],
                ],
            ],
        ],
        [
            'prompt' => 'Can I track it?',
            'checks' => [],
            'runs' => [
                ['run' => 1, 'passed' => true, 'tool_calls' => [], 'answer' => 'It ships with a tracking code.'],
            ],
        ],
    ]);
});

it('writes the context the driver describes next to the models', function () {
    SupportAgent::fake(['Your order shipped yesterday.']);

    $aiTest = makeAiTest('answers within the context of the driver');

    $aiTest
        ->driver(fn () => ScopedShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Where is my order?');

    $aiTest->execute();

    $result = resultFile('answers-within-the-context-of-the-driver');

    expect($result->get('context'))->toBe(['Scope' => 'All stores', 'Page' => 'Order details'])
        ->and(array_slice(array_keys($result->data), 3, 2))->toBe(['model', 'context']);
});

it('writes multi-line answers as literal blocks', function () {
    SupportAgent::fake(["Your order:\n\n- shipped yesterday\n- arrives tomorrow"]);

    $aiTest = makeAiTest('lists the order status');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is my order?');

    $aiTest->execute();

    expect(File::get(config('ai-tests.results_path').'/lists-the-order-status.yaml'))
        ->toEndWith("        answer: |-\n          Your order:\n\n          - shipped yesterday\n          - arrives tomorrow\n");
});

it('fails with a pointer to the result file when the pass rate is too low', function () {
    SupportAgent::fake(['Your order shipped yesterday.', 'Your order shipped yesterday after a long delay in the warehouse.']);

    JudgeAgent::fake([
        ['verdicts' => [['criterion' => 1, 'reason' => 'Mentions the shipping date.', 'pass' => true]]],
        ['verdicts' => [['criterion' => 1, 'reason' => 'Blames the warehouse.', 'pass' => false]]],
    ]);

    $aiTest = makeAiTest('answers briefly');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(2)
        ->minPassRate(1)
        ->user('Where is my order?')
        ->expectMaxWords(5)
        ->expectRubric('Does not blame anyone.');

    expect(fn () => $aiTest->execute())->toThrow(
        AssertionFailedError::class,
        '1/2 runs passed, below the minimum pass rate of 1. See '.config('ai-tests.results_path').'/answers-briefly.yaml.',
    );

    $result = resultFile('answers-briefly');

    expect($result)
        ->get('passed')->toBeFalse()
        ->get('pass_rate')->toBe(0.5);

    expect($result->get('turns')[0]['checks'][0])->toBe([
        'label' => 'At most 5 words',
        'kind' => 'max_words',
        'passed_runs' => 1,
        'runs' => [1 => true, 2 => false],
    ]);

    expect($result->get('turns')[0]['runs'][1]['verdicts'])->toBe([
        ['check' => 'At most 5 words', 'passed' => false, 'reason' => 'Answered in 11 words.'],
        ['check' => 'Does not blame anyone.', 'passed' => false, 'reason' => 'Blames the warehouse.'],
    ]);
});

it('passes the driver to the prompt closures', function () {
    SupportAgent::fake(['Order A-1 shipped.']);

    $aiTest = makeAiTest('uses the driver data in prompts');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user(fn (ShopDriver $shop) => "I am {$shop->customer->name}. Where is order A-{$shop->customer->id}?");

    $aiTest->execute();

    SupportAgent::assertPrompted('I am Ada. Where is order A-1?');

    expect(resultFile('uses-the-driver-data-in-prompts')->get('turns')[0]['prompt'])->toBe('I am Ada. Where is order A-1?');
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

    $aiTest = makeAiTest('uses the driver data in expectations');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(2)
        ->user('Where is my order?')
        ->expectTools(called: fn ($driver) => [LookupOrderTool::class], maxCalls: fn () => 1)
        ->expectMentions(function (ShopDriver $shop) use (&$resolvedTimes) {
            $resolvedTimes++;

            return "A-{$shop->customer->id}";
        })
        ->expectRubric(fn (ShopDriver $shop) => "Greets {$shop->customer->name} by name.");

    $aiTest->execute();

    expect($resolvedTimes)->toBe(1);

    expect(array_column(resultFile('uses-the-driver-data-in-expectations')->get('turns')[0]['checks'], 'label'))->toBe([
        'Calls `lookup_order`',
        'At most 1 tool calls',
        'Mentions `A-1`',
        'Greets Ada by name.',
    ]);

    JudgeAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, '1. Greets Ada by name.'));
});

it('fails before the first run when an expectation closure returns no usable value', function () {
    $aiTest = makeAiTest('expects a missing order');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Where is my order?')
        ->expectMentions(fn () => null);

    expect(fn () => $aiTest->execute())->toThrow(
        LogicException::class,
        'The AI test "expects a missing order" has a check that can\'t be set up. A closure given to expectMentions() returned null.',
    );

    SupportAgent::assertNeverPrompted();
});

it('rolls back the database after each run', function () {
    $conversationCounts = [];

    SupportAgent::fake(function () use (&$conversationCounts) {
        $conversationCounts[] = DB::table('agent_conversations')->count();

        return 'Hello again.';
    });

    $aiTest = makeAiTest('starts every run with a clean database');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(2)
        ->user('Hi')
        ->user('Hi again')
        ->user('Still there?');

    $aiTest->execute();

    expect($conversationCounts)->toBe([0, 1, 1, 0, 1, 1]);
});

it('crashes a follow-up turn when the agent does not remember the conversation', function () {
    SupportAgent::fake(['Hello.', 'Hello again.']);

    $aiTest = makeAiTest('forgets the first turn');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Hi')
        ->user('Hi again');

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class, 'does not remember the conversation');

    expect(resultFile('forgets-the-first-turn')->get('turns')[1]['runs'][0]['crash'])
        ->toStartWith('LogicException: The agent '.SupportAgent::class.' does not remember the conversation');
});

it('records a crashed run and keeps counting the other runs', function () {
    $promptCount = 0;

    SupportAgent::fake(function () use (&$promptCount) {
        if (++$promptCount === 1) {
            throw new RuntimeException('The provider is down.');
        }

        return 'Your order shipped yesterday.';
    });

    $aiTest = makeAiTest('survives a crash');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(2)
        ->minPassRate(0.5)
        ->user('Where is my order?')
        ->user('Thanks!');

    $aiTest->execute();

    $result = resultFile('survives-a-crash');

    expect($result->get('passed_runs'))->toBe(1);

    expect($result->get('turns')[0]['runs'][0])->toBe([
        'run' => 1,
        'passed' => false,
        'crash' => 'RuntimeException: The provider is down.',
    ]);

    expect($result->get('turns')[1]['runs'][0])->toBe(['run' => 1, 'passed' => false, 'not_reached' => true]);
});

it('leaves runs the judge could not grade out of the pass rate and keeps their transcript', function () {
    SupportAgent::fake(['Your order shipped yesterday.', 'Your order shipped yesterday.']);

    $judgeCalls = 0;

    JudgeAgent::fake(function () use (&$judgeCalls) {
        if (++$judgeCalls === 1) {
            throw new RuntimeException('The judge is rate limited.');
        }

        return ['verdicts' => [['criterion' => 1, 'reason' => 'It says when.', 'pass' => true]]];
    });

    $aiTest = makeAiTest('survives a judge error');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(2)
        ->minPassRate(1)
        ->user('Where is my order?')
        ->expectMaxWords(10)
        ->expectRubric('Says when the order shipped.');

    $aiTest->execute();

    $result = resultFile('survives-a-judge-error');

    expect($result->data)->toMatchArray([
        'runs' => 2,
        'passed_runs' => 1,
        'unjudged_runs' => 1,
        'pass_rate' => 1.0,
        'passed' => true,
    ]);

    expect($result->get('turns')[0]['checks'])->toBe([
        ['label' => 'At most 10 words', 'kind' => 'max_words', 'passed_runs' => 2, 'runs' => [1 => true, 2 => true]],
        ['label' => 'Says when the order shipped.', 'kind' => 'rubric', 'passed_runs' => 1, 'runs' => [2 => true]],
    ]);

    expect($result->get('turns')[0]['runs'][0])->toBe([
        'run' => 1,
        'passed' => false,
        'judge_error' => 'RuntimeException: The judge is rate limited.',
        'tool_calls' => [],
        'answer' => 'Your order shipped yesterday.',
    ]);
});

it('fails with the judge errors when no run could be judged', function () {
    SupportAgent::fake(['Your order shipped yesterday.']);

    JudgeAgent::fake(fn () => throw new RuntimeException('The judge is rate limited.'));

    $aiTest = makeAiTest('cannot be judged');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is my order?')
        ->expectRubric('Says when the order shipped.');

    expect(fn () => $aiTest->execute())->toThrow(
        AssertionFailedError::class,
        'No run could be judged, because the judge failed in every run. See '.config('ai-tests.results_path')."/cannot-be-judged.yaml.\nThe judge failed on run 1, turn 1: RuntimeException: The judge is rate limited.",
    );
});

it('fails with the crash and keeps the transcript of the crashed turn', function () {
    SupportAgent::fake(fn () => AgentResponse::fakeWithPendingApprovals([new PendingApproval('call-1', 'cancel_order', ['order' => '1234'])]));

    $aiTest = makeAiTest('keeps asking for approval');

    $aiTest
        ->driver(fn () => ShopDriver::forNewCustomer())
        ->runs(1)
        ->user('Cancel order 1234.');

    expect(fn () => $aiTest->execute())->toThrow(
        AssertionFailedError::class,
        'Run 1 crashed: LogicException: The agent kept asking for approval after 5 rounds.',
    );

    expect(resultFile('keeps-asking-for-approval')->get('turns')[0]['runs'][0])->toMatchArray([
        'crash' => 'LogicException: The agent kept asking for approval after 5 rounds.',
        'tool_calls' => [
            ['name' => 'cancel_order', 'arguments' => ['order' => '1234'], 'status' => 'pending', 'approval' => 'rejected'],
        ],
    ]);
});

it('passes with the default runs and minimum pass rate when two of three runs pass', function () {
    SupportAgent::fake(['Shipped.', 'Your order shipped yesterday afternoon.', 'Shipped.']);

    $aiTest = makeAiTest('passes two of three runs');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->user('Where is my order?')
        ->expectMaxWords(2);

    $aiTest->execute();

    expect(resultFile('passes-two-of-three-runs')->data)->toMatchArray([
        'runs' => 3,
        'passed_runs' => 2,
        'pass_rate' => 0.67,
        'min_pass_rate' => 0.66,
        'passed' => true,
    ]);
});

it('compares the minimum pass rate with the unrounded pass rate', function () {
    SupportAgent::fake(['Shipped.', 'Your order shipped yesterday afternoon.', 'Shipped.']);

    $aiTest = makeAiTest('just misses the minimum pass rate');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->minPassRate(0.67)
        ->user('Where is my order?')
        ->expectMaxWords(2);

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class, '2/3 runs passed');
});

it('forces the number of runs over the runs of the test', function () {
    config(['ai-tests.forced_runs' => '1']);

    SupportAgent::fake(['Shipped.', 'Shipped yesterday afternoon by courier.']);

    $aiTest = makeAiTest('runs once');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(3)
        ->minPassRate(1)
        ->user('Where is my order?')
        ->expectMaxWords(2);

    $aiTest->execute();

    expect(resultFile('runs-once')->data)->toMatchArray([
        'runs' => 1,
        'passed_runs' => 1,
        'pass_rate' => 1.0,
        'passed' => true,
    ]);
});

it('refuses an invalid number of runs or minimum pass rate', function (int $runs, float $minPassRate, string $message) {
    $aiTest = makeAiTest('is misconfigured');

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs($runs)
        ->minPassRate($minPassRate)
        ->user('Where is my order?');

    expect(fn () => $aiTest->execute())->toThrow(LogicException::class, $message);
})->with([
    'no runs' => [0, 0.5, 'needs at least one run, but has 0'],
    'a pass rate above 1' => [3, 66, 'must be between 0 and 1, but is 66'],
    'a negative pass rate' => [3, -0.5, 'must be between 0 and 1, but is -0.5'],
]);
