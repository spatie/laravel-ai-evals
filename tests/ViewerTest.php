<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Judge\JudgeAgent;
use Spatie\LaravelAiTests\Tests\TestSupport\LookupOrderTool;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopSeeder;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;
use Symfony\Component\Yaml\Exception\ParseException;

beforeEach(function () {
    $this->app['env'] = 'local';

    $responses = [
        new ToolCall('call-1', 'lookup_order', ['order' => '1234']),
        "Order **1234** shipped yesterday.\n\n<script>alert('answer')</script>",
        'Use the tracking link in your email.',
    ];

    SupportAgent::fake(function () use (&$responses) {
        return array_shift($responses) ?? throw new RuntimeException('The provider is down.');
    });

    JudgeAgent::fake([
        ['verdicts' => [['criterion' => 1, 'reason' => 'It does not give a delivery date.', 'pass' => false]]],
    ]);

    $aiTest = makeAiTest('tells a customer where their order is');

    $aiTest
        ->seed(ShopSeeder::class)
        ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->user))
        ->runs(2)
        ->user('Where is order <b>1234</b>?')
        ->expectTools(called: [LookupOrderTool::class])
        ->expectRubric('Gives the delivery date.')
        ->user('Can I track it?')
        ->expectMaxWords(20);

    expect(fn () => $aiTest->execute())->toThrow(AssertionFailedError::class);
});

afterEach(function () {
    $this->app['env'] = 'testing';
});

it('lists the result files', function () {
    $this->get('/ai-tests')
        ->assertOk()
        ->assertSee('Tells a customer where their order is')
        ->assertSee('gpt-')
        ->assertSee('/ai-tests/tells-a-customer-where-their-order-is');
});

it('renders every run of every turn, including a crash', function () {
    $this->get('/ai-tests/tells-a-customer-where-their-order-is')
        ->assertOk()
        ->assertSee('0 of 2 runs passed')
        ->assertSeeInOrder([
            'Turn 1',
            'Where is order &lt;b&gt;1234&lt;/b&gt;?',
            'Calls <code>lookup_order</code>',
            'Failure reasons',
            'Gives the delivery date.',
            'Run 1',
            'It does not give a delivery date.',
            'Crashes',
            'Run 2',
            'RuntimeException: The provider is down.',
            'Runs',
            'id="turn-1-run-1"',
            'lookup_order',
            '(order: &quot;1234&quot;)',
            'Order <strong>1234</strong> shipped yesterday.',
            'id="turn-1-run-2"',
            'RuntimeException: The provider is down.',
            'Turn 2',
            'Can I track it?',
            'At most 20 words',
            'Use the tracking link in your email.',
            'id="turn-2-run-2"',
            'This run crashed in an earlier turn.',
        ], escape: false)
        ->assertDontSee("<script>alert('answer')</script>", escape: false);
});

it('links to the neighbouring tests in the order of the list', function () {
    writeResultFile('alpha', ['title' => 'Alpha', 'passed' => true]);
    writeResultFile('beta', ['title' => 'Beta', 'passed' => true]);
    writeResultFile('zulu', ['title' => 'Zulu', 'passed' => false]);

    $this->get('/ai-tests')->assertSeeInOrder(['Tells a customer where their order is', 'Zulu', 'Alpha', 'Beta']);

    $this->get('/ai-tests/alpha')
        ->assertSeeInOrder(['Previous test: Zulu', '3 of 4', 'Next test: Beta'])
        ->assertSee('href="http://localhost/ai-tests/zulu"', escape: false)
        ->assertSee('href="http://localhost/ai-tests/beta"', escape: false);
});

it('only links forward from the first test and back from the last', function () {
    writeResultFile('alpha', ['title' => 'Alpha', 'passed' => true]);
    writeResultFile('zulu', ['title' => 'Zulu', 'passed' => false]);

    $this->get('/ai-tests/tells-a-customer-where-their-order-is')
        ->assertSeeInOrder(['1 of 3', 'Next test: Zulu'])
        ->assertDontSee('Previous test:')
        ->assertDontSee('rel="prev"', escape: false);

    $this->get('/ai-tests/alpha')
        ->assertSeeInOrder(['Previous test: Zulu', '3 of 3'])
        ->assertDontSee('Next test:')
        ->assertDontSee('rel="next"', escape: false);
});

it('returns a 404 for unknown or unsafe slugs', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/ai-tests/missing',
    '/ai-tests/..%2F..%2F.env',
    '/ai-tests/Tells-A-Customer-Where-Their-Order-Is',
]);

it('returns a 404 outside the local environment', function () {
    $this->app['env'] = 'production';

    $this->get('/ai-tests')->assertNotFound();
});

it('shows a run the judge could not grade as unjudged', function () {
    writeResultFile('unjudged', [
        'title' => 'Unjudged',
        'passed' => true,
        'runs' => 2,
        'passed_runs' => 1,
        'unjudged_runs' => 1,
        'turns' => [[
            'prompt' => 'Where is my order?',
            'runs' => [
                ['run' => 1, 'passed' => false, 'judge_error' => 'RuntimeException: The judge is rate limited.', 'tool_calls' => [], 'answer' => 'It shipped.'],
                ['run' => 2, 'passed' => true, 'tool_calls' => [], 'answer' => 'It shipped.'],
            ],
        ]],
    ]);

    $this->get('/ai-tests/unjudged')
        ->assertOk()
        ->assertSee('1 unjudged, left out of the pass rate')
        ->assertSeeInOrder(['Judge errors', 'Run 1', 'RuntimeException: The judge is rate limited.'])
        ->assertSee('Run 1, unjudged');
});

it('reports an unreadable result file and still renders the viewer', function () {
    Exceptions::fake();

    File::put(config('ai-tests.results_path').'/broken.yaml', "title: Broken\n<<<<<<< HEAD\npassed: [true\n");
    writeResultFile('odd-values', ['title' => ['not', 'a', 'string'], 'model' => ['gpt'], 'passed' => true]);

    $this->get('/ai-tests')
        ->assertOk()
        ->assertSeeInOrder(['broken', 'Unreadable result file'])
        ->assertSee('odd-values');

    $this->get('/ai-tests/broken')
        ->assertOk()
        ->assertSee('This result file could not be read.');

    Exceptions::assertReported(ParseException::class);
});
