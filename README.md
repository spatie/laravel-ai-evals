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
| `expectMentions()` | Values the answer must contain, such as an order number or a link. |
| `expectMentionsInOrder()` | Values the answer must contain in this order. |
| `expectNotMentions()` | Values the answer must not contain, in any letter case, such as an outdated term. |
| `expectRubric()` | One or more criteria for the LLM judge. |
| `expectThat()` | A check written in code, with a label. See [checking with code](#checking-with-code). |
| `approveAll()` | Approves every tool call that waits for approval in this turn. Without it, they are rejected. |

Tool checks count every tool call of the turn, including the calls after an approval. A tool class is matched by its `name()`, or by its class name when it has no public `name()` method. Tool names are resolved before the first run, so a tool class the container can't build fails the test right away. Words are tokens with at least one letter or digit, so Markdown bullets do not count. A mention is a case-sensitive match anywhere in the answer of the turn. For `expectMentionsInOrder()`, the first mention of each value must come after the first mention of the value before it. Unlike the other mention checks, `expectNotMentions()` ignores case, so `error group` also catches "Error groups", and its failure quotes the text it found. Write rubric criteria as concrete, checkable statements. A vague criterion gives noisy results. The judge grades one turn at a time. For a later turn, it also gets the earlier turns of the run (their prompts, tool calls without results, and answers) as context, so a criterion can refer to "those orders", but only the current turn is graded.

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

### Checking known values

The `expectTools()`, `expectMentions()`, `expectMentionsInOrder()`, `expectNotMentions()` and `expectRubric()` arguments can be closures too. Like a `user()` closure, they receive the driver and return the value, or an array of values. They run once per test, after the `driver()` closure and before the runs, so the result file shows the values they returned. This lets a test check the answer against the data it created, not only against what the tools returned. Say the seeder of the `ShopDriver` above also keeps its orders by a reference:

```php
class ShopDriver extends AgentDriver
{
    // ...

    public function orderUrl(string $reference): string
    {
        return route('orders.show', $this->seeder->orders[$reference]);
    }
}

aiTest('links to a late order')
    ->driver(fn () => ShopDriver::seeded())
    ->user('Which of my orders is late?')
    ->expectMentions(fn (ShopDriver $driver) => $driver->orderUrl('late-order'))
    ->expectRubric(fn (ShopDriver $driver) => "Says that order {$driver->seeder->orders['late-order']->number} is late, and why.");
```

Prefer a mention over a rubric criterion for exact values the agent must output, such as links to records, IDs or counts. A mention costs nothing, does not depend on the judge, and its failure names the missing value. Use a rubric criterion when the wording can vary.

`expectNotMentions()` does the same for words the answer must avoid, such as a term your product no longer uses:

```php
aiTest('uses the current terminology')
    ->driver(fn () => ShopDriver::seeded())
    ->user('Which of my orders need attention?')
    ->expectNotMentions('shipment group', 'parcel set');
```

### Checking with code

When a check needs more than the answer, such as the exact arguments of a tool call or the rows a tool changed, write it in code with `expectThat()`. Give it a label and a closure:

```php
use Spatie\LaravelAiTests\Transcript\Transcript;

aiTest('cancels only the late orders')
    ->driver(fn () => ShopDriver::seeded())
    ->user('Cancel all my late orders.')
    ->approveAll()
    ->expectThat('Cancels exactly the late orders', function (ShopDriver $driver) {
        $cancelled = $driver->seeder->customer->orders()->where('status', 'cancelled')->pluck('number')->all();

        return $cancelled === ['A-1', 'A-3'] ?: 'Cancelled '.implode(', ', $cancelled).'.';
    })
    ->expectThat('Looks up each order once', function (Transcript $transcript) {
        $orders = array_column(array_column($transcript->toolCalls, 'arguments'), 'order');

        return count($orders) === count(array_unique($orders));
    });
```

The closure receives the driver and the `Transcript` of the turn, by their class or as `$driver` and `$transcript`. The transcript has the prompt, the tool calls with their arguments, result, status and approval (`$transcript->toolCalls`), and the answer (`$transcript->answer()`). Return `true` to pass, `false` to fail, or a string to fail with that string as the reason. When the closure throws an exception, for example a failed `expect()`, the check fails with the message of the exception. The run goes on.

Unlike the other closures, this one runs in every run, right after the turn and before the database transaction of the run is rolled back. It sees the rows the run created or changed. It runs in a nested transaction, so a failed query does not break the rest of the run. In the result file and the viewer, the check shows with its label and kind `code`, like the other checks.

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

### Describing the context

A driver can describe the context a test runs in, for example which data is in scope or which page the user is on. Implement `DescribesContext` and return labels with their values:

```php
use Spatie\LaravelAiTests\Drivers\DescribesContext;

class ChatDriver implements Driver, DescribesContext
{
    // ...

    public function context(): array
    {
        return [
            'Scope' => $this->project ? $this->project->name : 'All projects',
            'Page' => 'Error details',
        ];
    }
}
```

The package reads the context once per test, after the `driver()` closure returns the driver. It is written to the result file and shown in the viewer, in the list under the test title and in the header of the result.

## Running the tests

```bash
vendor/bin/pest --group=ai
vendor/bin/pest --group=ai --filter="cancels an order"
```

When a test is below its minimum pass rate, it fails with a message that points to its result file.

## Results

Every test writes `<results_path>/<slug>.yaml`, where the slug is `Str::slug()` of the description. A new run overwrites the file. Commit the result files, so a diff shows how a prompt or tool change moved the results.

Two AI tests whose descriptions give the same slug, even in different files, throw an error when Pest loads them, so one can't overwrite the result of the other. When you rename or delete a test, its old result file stays, because the package can't tell a removed test from one that was not part of this run, for example with `--filter`. Delete the old file by hand.

The file starts with the facts of the test: `slug`, `title`, `test_file`, `model`, `judge_model`, `context` (only when the driver describes it), `runs`, `passed_runs`, `unjudged_runs` (only when there are any), `pass_rate`, `min_pass_rate`, `passed` and `ran_at`. Then each turn has its prompt, the checks with the result of every run, and every run with its tool calls, its answer and the verdicts. Verdicts list every judged criterion and every failed check, each with its reason. Multi-line answers are written as literal blocks, so they read like the original text. The `status` of a tool call is `completed`, `failed`, `rejected`, `pending` when it still waits for approval, or `no_result` when no result came back for it. The judge only accepts a claimed change when its tool call completed.

```yaml
slug: tells-a-customer-where-their-order-is
title: 'Tells a customer where their order is'
test_file: tests/AiTests/OrderTest.php
model: gpt-5.4
judge_model: gpt-5.4
context:
  Scope: 'All stores'
  Page: 'Order details'
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
      - label: 'Mentions `https://shop.test/orders/1234`'
        kind: mentions
        passed_runs: 1
        runs:
          1: true
          2: false
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
          [Order **1234**](https://shop.test/orders/1234) shipped yesterday.

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
        'runner' => env('AI_TESTS_JUDGE_RUNNER', 'agent'),
        'provider' => env('AI_TESTS_JUDGE_PROVIDER'),
        'model' => env('AI_TESTS_JUDGE_MODEL'),
        'claude' => [
            'binary' => env('AI_TESTS_JUDGE_CLAUDE_BINARY', 'claude'),
            'model' => env('AI_TESTS_JUDGE_CLAUDE_MODEL'),
            'timeout' => 300,
        ],
        'codex' => [
            'binary' => env('AI_TESTS_JUDGE_CODEX_BINARY', 'codex'),
            'model' => env('AI_TESTS_JUDGE_CODEX_MODEL'),
            'timeout' => 300,
        ],
    ],

    'viewer' => [
        'enabled' => true,
        'prefix' => 'ai-tests',
    ],
];
```

The judge uses your default Laravel AI provider and model unless you set them. To give the judge context about your product, extend `Spatie\LaravelAiTests\Judge\JudgeAgent`, override `instructions()`, and set your class as `judge.agent`.

### Judging with a local CLI

The judge can also run through [Claude Code](https://code.claude.com) or [Codex](https://developers.openai.com/codex/cli) on your machine instead of the API. You then judge with your own subscription, and with a different model than the agent under test. Only the judge changes. The agent under test still runs through its driver.

```bash
AI_TESTS_JUDGE_RUNNER=claude AI_TESTS_JUDGE_CLAUDE_MODEL=opus vendor/bin/pest --group=ai
AI_TESTS_JUDGE_RUNNER=codex vendor/bin/pest --group=ai
```

The CLI gets the instructions and the JSON schema of your `judge.agent` class and the same prompt, so a custom judge agent works unchanged. `judge.provider` and `judge.model` only apply to the `agent` runner. Without a model, the CLI uses its default. `judge_model` in the result file names the model and the CLI, such as `claude-opus-5-5 via Claude Code`. A non-zero exit or output that does not match the schema becomes a `judge_error`, like an API error.

Every judge call starts a new CLI process in a new empty temporary directory, which is deleted afterwards. The prompt goes in through stdin.

Claude Code runs as `claude --print --output-format json --json-schema <schema> --system-prompt <instructions> --tools "" --strict-mcp-config --setting-sources "" --safe-mode --disable-slash-commands --no-session-persistence [--model <model>]`. The system prompt replaces the Claude Code one. `--tools ""` removes the built-in tools, `--strict-mcp-config` loads no MCP servers, `--setting-sources ""` skips user, project and local settings, `--safe-mode` turns off CLAUDE.md files, skills, plugins and hooks, and `--no-session-persistence` keeps the session off disk. Managed (policy) settings still apply. Claude Code still adds a short environment block, such as the working directory and the date.

Codex runs as `codex exec --ephemeral --skip-git-repo-check --ignore-user-config --ignore-rules --sandbox read-only --cd <empty directory> --config model_instructions_file=<instructions> --output-schema <schema> --output-last-message <file> [--model <model>] -`, with these extra options:

- `--config project_doc_max_bytes=0` skips `AGENTS.md` files in the working directory and its parents.
- `--config skills.include_instructions=false`, `--config include_apps_instructions=false` and `--config include_environment_context=false` leave out the skill list, the apps instructions and the environment description.
- `--config web_search="disabled"` and `--disable` for `shell_tool`, `unified_exec`, `code_mode_host`, `multi_agent`, `apps`, `plugins`, `hooks` and `image_generation` remove the tools.

`--ignore-user-config` skips `config.toml`, with its MCP servers and its default model. Codex can't fully isolate the judge: it still reads the global `$CODEX_HOME/AGENTS.md` and still offers a few built-in tools. Commands can't run, and the sandbox is read-only. To also drop the global `AGENTS.md`, point `CODEX_HOME` at a separate directory where you ran `codex login`. Codex feature names change between versions, so a newer or older Codex can reject an option. The judge error then shows why.

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
