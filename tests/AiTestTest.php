<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Judge\JudgeAgent;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopSeeder;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

it('writes a result file for a passing test', function () {
    SupportAgent::fake(['Your order shipped yesterday.', 'It ships with a tracking code.']);

    JudgeAgent::fake([
        ['verdicts' => [['criterion' => 1, 'reason' => 'It says when.', 'pass' => true]]],
    ]);

    $aiTest = makeAiTest('answers a question about an order');

    $aiTest
        ->seed(ShopSeeder::class)
        ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->user))
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
            'seeder' => 'ShopSeeder',
            'runs' => 1,
            'passed_runs' => 1,
            'pass_rate' => 1.0,
            'min_pass_rate' => 0.66,
            'passed' => true,
        ])
        ->toHaveKeys(['model', 'judge_model', 'test_file', 'ran_at']);

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

it('passes the seeder and the setUp values to later closures', function () {
    SupportAgent::fake(['Order A-1 shipped.']);

    $aiTest = makeAiTest('uses seeded data in prompts');

    $aiTest
        ->seed(ShopSeeder::class)
        ->setUp(fn (ShopSeeder $seeder) => ['orderNumber' => "A-{$seeder->user->id}"])
        ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->user))
        ->runs(1)
        ->user(fn (ShopSeeder $seeder, string $orderNumber) => "I am {$seeder->user->name}. Where is order {$orderNumber}?");

    $aiTest->execute();

    SupportAgent::assertPrompted('I am Ada. Where is order A-1?');

    expect(resultFile('uses-seeded-data-in-prompts')->get('turns')[0]['prompt'])->toBe('I am Ada. Where is order A-1?');
});

it('rolls back the database after each run', function () {
    $conversationCounts = [];

    SupportAgent::fake(function () use (&$conversationCounts) {
        $conversationCounts[] = DB::table('agent_conversations')->count();

        return 'Hello again.';
    });

    $aiTest = makeAiTest('starts every run with a clean database');

    $aiTest
        ->seed(ShopSeeder::class)
        ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->user))
        ->runs(2)
        ->user('Hi')
        ->user('Hi again');

    $aiTest->execute();

    expect($conversationCounts)->toBe([0, 1, 0, 1]);
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
        ->driver(new AgentDriver(SupportAgent::class))
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
        ->seed(ShopSeeder::class)
        ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->user))
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
