# Test your Laravel AI agents against real models with pass rates and readable results

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-ai-tests.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-ai-tests)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-ai-tests/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/laravel-ai-tests/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-ai-tests.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-ai-tests)

Unit tests with faked responses tell you that your code works. They don't tell you whether your agent gives good answers. This package lets you write Pest tests that talk to your [Laravel AI](https://github.com/laravel/ai) agent with the real model, run every conversation a few times, check each answer, and write a readable Markdown result file per test.

```php
aiTest('tells a customer where their order is')
    ->setUp(fn () => ['customer' => Customer::factory()->hasOrders(1)->create()])
    ->driver(fn (Customer $customer) => new AgentDriver(SupportAgent::class, as: $customer))
    ->user('Where is my order?')
    ->expectTools(called: [LookupOrderTool::class], maxCalls: 3)
    ->expectMaxWords(80)
    ->expectRubric('Says when the order shipped.');
```

A test passes when enough of its runs pass. A run passes when all deterministic checks pass and an LLM judge accepts every rubric criterion.

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
    ->setUp(function () {
        $customer = Customer::factory()->create();

        return [
            'customer' => $customer,
            'order' => Order::factory()->for($customer)->create(['number' => 'A-1001']),
        ];
    })
    ->driver(fn (Customer $customer) => new AgentDriver(SupportAgent::class, as: $customer))
    ->runs(3)
    ->minPassRate(0.66)
    ->user(fn (Order $order) => "Please cancel order {$order->number}.")
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
| `setUp()` | Builds the data for this test, usually with factories. Runs once per test. |
| `seed()` | Runs a database seeder once per test, like `$this->seed()`. |
| `driver()` | How the turns reach your agent. Pass a driver or a closure that returns one. |
| `runs()` | Number of fresh conversations. Defaults to `ai-tests.runs`. |
| `minPassRate()` | Minimum share of passing runs. Defaults to `ai-tests.min_pass_rate`. |
| `user()` | Starts a turn with this message. Pass a closure when the message depends on the test data. |
| `expectTools()` | Tool classes or tool names that must or must not be called, and a maximum number of calls. |
| `expectMaxWords()` | Maximum number of words in the answer of the turn. |
| `expectRubric()` | One or more criteria for the LLM judge. |
| `approveAll()` | Approves every tool call that waits for approval in this turn. Without it, they are rejected. |

Tool checks count every tool call of the turn, including the calls after an approval. Words are tokens with at least one letter or digit, so Markdown bullets do not count. Write rubric criteria as concrete, checkable statements. A vague criterion gives noisy results.

### Test data

Use `setUp()` with factories for data that belongs to one test. Its return value is passed to the closures of `driver()` and `user()`:

- Return an array to pass each value by its key: `fn (Order $order)` receives `$data['order']`.
- Return an object to pass it by its class.

Use a seeder when the setup is large or shared between many tests. `seed()` runs it once per test and keeps the instance, so the seeder can expose what it created as public properties. Closures receive it by its class or as `$seeder`. When you use both, `setUp()` runs after the seeder and can receive it too.

```php
class ShopSeeder extends Seeder
{
    public Customer $customer;

    public function run(): void
    {
        $this->customer = Customer::factory()->hasOrders(25)->create();
    }
}

aiTest('summarizes the order history')
    ->seed(ShopSeeder::class)
    ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->customer))
    ->user('What did I order this year?')
    ->expectRubric('Mentions the number of orders.');
```

Each run is wrapped in a database transaction that is rolled back afterwards, so changes made by one run do not leak into the next. The data from `setUp()` and `seed()` is created once, before the runs.

## Drivers

`AgentDriver` works with any Laravel AI agent. Pass the agent class, or a closure that creates the agent, and the participant of the conversation:

```php
new AgentDriver(SupportAgent::class, as: $customer);

new AgentDriver(fn () => new SupportAgent(locale: 'nl'), as: $customer);
```

Every run starts a new conversation. For tests with more than one turn, or with tool approvals, the agent must remember conversations, using the `RemembersConversations` trait, and you must pass `as`.

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

Every test writes `<results_path>/<slug>.md`, where the slug is `Str::slug()` of the description. A new run overwrites the file. Commit the result files, so a diff shows how a prompt or tool change moved the results.

The file starts with flat YAML frontmatter: `slug`, `title`, `test_file`, `seeder`, `model`, `judge_model`, `runs`, `passed_runs`, `pass_rate`, `min_pass_rate`, `passed` and `ran_at`. Each turn then has the user message, a table with the passed runs per check, the judge notes for failed criteria, crashes, and an example answer. The example is the first failing run, or run 1 when every run passed.

When a run throws an exception, the run fails and the result file lists the exception. The other runs still count.

### Viewing results

In the `local` environment, the package registers a small results viewer at `/ai-tests`. It lists the result files and renders each one. Outside the local environment it returns a 404. Disable it with `ai-tests.viewer.enabled`.

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
