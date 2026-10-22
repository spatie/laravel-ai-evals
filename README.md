# Test your Laravel AI agents against real models with pass rates and readable results

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-ai-tests.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-ai-tests)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-ai-tests/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/laravel-ai-tests/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-ai-tests.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-ai-tests)

Unit tests with faked responses tell you that your code works. They don't tell you whether your agent gives good answers. This package lets you write Pest tests that talk to your [Laravel AI](https://github.com/laravel/ai) agent with the real model, run every conversation a few times, check each answer, and write a readable YAML result file per test.

```php
aiTest('tells a customer where their order is')
    ->driver(fn () => new AgentDriver(SupportAgent::class, as: Customer::factory()->hasOrders(1)->create()))
    ->user('Where is my order?')
    ->expectTools(called: [LookupOrderTool::class], maxCalls: 3)
    ->expectMaxWords(80)
    ->expectRubric('Says when the order shipped.');
```

A test passes when enough of its runs pass. A run passes when all deterministic checks pass and an LLM judge accepts every rubric criterion.

## What these tests are for

These tests check your agent, not the model. They cover the parts you own: the tools, their descriptions and the data they return, the context you pass, and the format and behaviour your system prompt pushes the model towards. They do not measure what the model knows or how well it reasons.

A good rule of thumb: every check and rubric line should point at something you own and could change to fix a failure. If nothing you own could fix it, drop the line.

```php
// Tests the model's reasoning. When it fails, there is nothing you can change.
->expectRubric('Explains why the property is null at line 14.')

// Tests your agent. When it fails, you fix the tool, its description or the prompt.
->expectTools(called: [GetErrorDetailsTool::class])
->expectRubric('Bases its explanation on the stack frame that the error details tool returned.')
```

## Support us

[<img src="https://github-ads.s3.eu-central-1.amazonaws.com/laravel-ai-tests.jpg?t=1" width="419px" />](https://spatie.be/github-ad-click/laravel-ai-tests)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-ai-tests --dev
```

AI tests call a real model, so they are slow, cost money, and are not deterministic. Keep them out of your normal test run. Put them in their own directory and group in `tests/Pest.php`:

```php
pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->group('ai')->in('AiTests');
```

Exclude the group in `phpunit.xml`:

```xml
<groups>
    <exclude>
        <group>ai</group>
    </exclude>
</groups>
```

Make sure the test environment has the API key of your AI provider.

## Writing a test

`aiTest()` registers a Pest test. Call the test methods first, then add one or more turns with `user()`. The methods after a `user()` call apply to that turn.

```php
use App\Ai\Agents\SupportAgent;
use App\Ai\Tools\CancelOrderTool;
use App\Ai\Tools\LookupOrderTool;
use App\Models\Customer;
use App\Models\Order;
use Spatie\LaravelAiTests\Drivers\AgentDriver;

aiTest('cancels an order after confirmation')
    ->driver(function () {
        $customer = Customer::factory()->create();

        Order::factory()->for($customer)->create(['number' => 'A-1001']);

        return new AgentDriver(SupportAgent::class, as: $customer);
    })
    ->runs(3)
    ->minPassRate(0.66)
    ->user('Please cancel order A-1001.')
    ->expectTools(called: [LookupOrderTool::class], notCalled: [CancelOrderTool::class])
    ->expectRubric('Asks the customer to confirm before cancelling.')
    ->user('Yes, cancel it.')
    ->approveAll()
    ->expectTools(called: [CancelOrderTool::class])
    ->expectMaxWords(60)
    ->expectRubric(
        'Confirms that order A-1001 was cancelled.',
        'Does not claim a refund was issued.',
    );
```

| Method | Behaviour |
| --- | --- |
| `driver()` | How the turns reach your agent. Pass a driver, or a closure that sets up the test data and returns one. |
| `runs()` | Number of fresh conversations. Defaults to `ai-tests.runs`. |
| `minPassRate()` | Minimum share of passing runs. Defaults to `ai-tests.min_pass_rate`. |
| `user()` | Starts a turn with this message. Pass a closure that receives the driver when the message depends on the test data. |
| `expectTools()` | Tool classes or tool names that must or must not be called, and a maximum number of calls. |
| `expectMaxWords()` | Maximum number of words in the answer of the turn. |
| `expectRubric()` | One or more criteria for the LLM judge. |
| `approveAll()` | Approves every tool call that waits for approval in this turn. Without it, they are rejected. |

Tool checks count every tool call of the turn, including the calls after an approval. A tool class is matched by its `name()`, or by its class name when it has no public `name()` method. Tool names are resolved before the first run, so a tool class the container can't build fails the test right away. Words are tokens with at least one letter or digit, so Markdown bullets do not count. Write rubric criteria as concrete, checkable statements. A vague criterion gives noisy results. The judge grades one turn at a time. For a later turn, it also gets the earlier turns of the run (their prompts, tool calls without results, and answers) as context, so a criterion can refer to "those orders", but only the current turn is graded.

### Test data

Set up the test data in the `driver()` closure, with factories, a seeder, or anything else, and return the driver. The closure runs once per test, before the runs. A `user()` closure receives the driver, by its class or as `$driver`, so a driver that keeps what was created can pass it to the prompts.

For data that is large or shared between many tests, give your driver a named constructor that runs a seeder and keeps it:

```php
class ShopSeeder extends Seeder
{
    public Customer $customer;

    public Order $order;

    public function run(): void
    {
        $this->customer = Customer::factory()->hasOrders(25)->create();

        $this->order = $this->customer->orders->first();
    }
}

class ShopDriver extends AgentDriver
{
    public function __construct(
        public readonly ShopSeeder $seeder,
    ) {
        parent::__construct(SupportAgent::class, as: $seeder->customer);
    }

    public static function seeded(): self
    {
        $seeder = app(ShopSeeder::class);

        $seeder->setContainer(app())->__invoke();

        return new self($seeder);
    }
}

aiTest('summarizes an order')
    ->driver(fn () => ShopDriver::seeded())
    ->user(fn (ShopDriver $driver) => "What is in order {$driver->seeder->order->number}?")
    ->expectRubric('Lists the products of the order.');
```

Each run is wrapped in a database transaction that is rolled back afterwards, so changes made by one run do not leak into the next. The data from the `driver()` closure is created once, before the runs.

## Drivers

`AgentDriver` works with any Laravel AI agent. Pass the agent class, or a closure that creates the agent, and the participant of the conversation:

```php
new AgentDriver(SupportAgent::class, as: $customer);

new AgentDriver(fn () => new SupportAgent(locale: 'nl'), as: $customer);
```

Every run starts a new conversation. For tests with more than one turn, or with tool approvals, the agent must remember conversations, using the `RemembersConversations` trait, and you must pass `as`. When it can't remember the conversation, the second turn crashes the run with an error that says so, instead of quietly starting a new conversation.

When your app talks to the agent in its own way, for example through an action that checks usage limits and adds page context, write a driver that uses that code path:

```php
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Responses\AgentResponse;
use Spatie\LaravelAiTests\Drivers\Driver;

class ChatDriver implements Driver
{
    protected Chat $chat;

    public function __construct(
        protected User $user,
    ) {}

    public function startConversation(): void
    {
        $this->chat = app(StartChatAction::class)->execute($this->user);
    }

    public function prompt(string $message): AgentResponse
    {
        return app(SendChatMessageAction::class)->execute($this->chat, $message);
    }

    public function decide(array $decisions): AgentResponse
    {
        return app(SendChatMessageAction::class)->execute($this->chat, Decisions::from($decisions));
    }
}
```

`decide()` receives the tool call IDs with `true` for approved and `false` for rejected calls. Return the agent response of each step, streamed or not. The package reads the text, tool calls, tool results and pending approvals from it.

## Running the tests

```bash
vendor/bin/pest --group=ai
vendor/bin/pest --group=ai --filter="cancels an order"
```

When a test is below its minimum pass rate, it fails with a message that points to its result file.

## Results

Every test writes `<results_path>/<slug>.yaml`, where the slug is `Str::slug()` of the description. A new run overwrites the file. Commit the result files, so a diff shows how a prompt or tool change moved the results.

Two AI tests whose descriptions give the same slug, even in different files, throw an error when Pest loads them, so one can't overwrite the result of the other. When you rename or delete a test, its old result file stays, because the package can't tell a removed test from one that was not part of this run, for example with `--filter`. Delete the old file by hand.

The file starts with the facts of the test: `slug`, `title`, `test_file`, `model`, `judge_model`, `runs`, `passed_runs`, `unjudged_runs` (only when there are any), `pass_rate`, `min_pass_rate`, `passed` and `ran_at`. Then each turn has its prompt, the checks with the result of every run, and every run with its tool calls, its answer and the verdicts. Verdicts list every judged criterion and every failed check, each with its reason. Multi-line answers are written as literal blocks, so they read like the original text. The `status` of a tool call is `completed`, `failed`, `rejected`, `pending` when it still waits for approval, or `no_result` when no result came back for it. The judge only accepts a claimed change when its tool call completed.

```yaml
slug: tells-a-customer-where-their-order-is
title: 'Tells a customer where their order is'
test_file: tests/AiTests/OrderTest.php
model: gpt-5.4
judge_model: gpt-5.4
runs: 2
passed_runs: 1
pass_rate: 0.5
min_pass_rate: 0.66
passed: false
ran_at: '2026-10-22T10:00:00+00:00'
turns:
  - prompt: 'Where is order 1234?'
    checks:
      - label: 'Calls `lookup_order`'
        kind: tool
        passed_runs: 2
        runs:
          1: true
          2: true
      - label: 'Says when the order shipped.'
        kind: rubric
        passed_runs: 1
        runs:
          1: true
          2: false
    runs:
      - run: 1
        passed: true
        tool_calls:
          - name: lookup_order
            arguments:
              order: '1234'
            status: completed
        answer: |-
          Order **1234** shipped yesterday.

          You will get a tracking link by email.
        verdicts:
          - check: 'Says when the order shipped.'
            passed: true
            reason: 'It says the order shipped yesterday.'
      - run: 2
        passed: false
        crash: 'RuntimeException: The provider is down.'
```

When a run throws an exception, the run fails and its entry in that turn has a `crash`, together with the tool calls and answer it got before the crash. Later turns mark the run as `not_reached`. The other runs still count.

When the judge itself fails, for example on a rate limit, it is not held against the agent. The turn keeps its tool calls and answer and gets a `judge_error`. A run that passed all its other checks but could not be judged is unjudged: it is left out of the pass rate and counted in `unjudged_runs`. A run that failed a check still counts as failed. When no run could be judged, the test fails. The failure message of a test lists every crash and judge error.

### Viewing results

In the `local` environment, the package registers a small results viewer at `/ai-tests`. It lists the result files and shows each one, with a tab per run. The first failing run is selected. Outside the local environment it returns a 404. Disable it with `ai-tests.viewer.enabled`. A result file that can't be parsed, for example after a merge conflict, is reported and listed as unreadable instead of breaking the viewer. Result files are written to a temporary file first and then moved into place, so the viewer never reads a half-written file.

## Configuration

You can publish the config file with:

```bash
php artisan vendor:publish --tag="ai-tests-config"
```

This is the content of the published config file:

```php
return [
    'results_path' => base_path('tests/AiTests/results'),

    'runs' => 3,

    'min_pass_rate' => 0.66,

    'judge' => [
        'agent' => JudgeAgent::class,
        'provider' => env('AI_TESTS_JUDGE_PROVIDER'),
        'model' => env('AI_TESTS_JUDGE_MODEL'),
    ],

    'viewer' => [
        'enabled' => true,
        'prefix' => 'ai-tests',
    ],
];
```

The judge uses your default Laravel AI provider and model unless you set them. To give the judge context about your product, extend `Spatie\LaravelAiTests\Judge\JudgeAgent`, override `instructions()`, and set your class as `judge.agent`.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/spatie/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Alex Vanderbist](https://github.com/AlexVanderbist)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
