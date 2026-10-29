# Evaluate conversations with your Laravel AI agents

[![Latest Version on Packagist](https://img.shields.io/packagist/v/spatie/laravel-ai-evals.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-ai-evals)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-ai-evals/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/spatie/laravel-ai-evals/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/spatie/laravel-ai-evals/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/spatie/laravel-ai-evals/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/spatie/laravel-ai-evals.svg?style=flat-square)](https://packagist.org/packages/spatie/laravel-ai-evals)

Unit tests with faked responses tell you that your code works. They don't tell you whether your agent gives good answers. This Pest plugin lets you write evals that talk to your [Laravel AI](https://github.com/laravel/ai) agent with the real model, hold a conversation of one or more turns, check every answer, and repeat the conversation a few times.

```php
evaluate('tells a customer where their order is')
    ->agent(fn () => new AgentDriver(SupportAgent::class, as: Customer::factory()->hasOrders(1)->create()))
    ->repeat(3)
    ->prompt('Where is my order?')
    ->toHaveToolCalls([LookupOrderTool::class])
    ->toHaveMaxWords(80)
    ->toSatisfy('Says when the order shipped.')
    ->prompt('Can you send it to my office instead?')
    ->toSatisfy('Explains that a shipped order can no longer change address.');
```

An eval passes when every run passes. A run passes when every check of every turn passes, and an LLM judge accepts every `toSatisfy()` criterion. Like Pest evals, it stops at the first failure: a turn that fails a check skips the judge and ends its run, and a failed run ends the eval.

> [!NOTE]
> Pest has its own [evals plugin](https://pestphp.com/docs/evals). Use that one when a single prompt is enough. This package only exists because Pest evals have no multi-turn conversations. It uses the same concepts and names where it can: the `--evals` flag, `prompt()`, `toSatisfy()`, `repeat()` and the judge environment variables.

## Support us

[<img src="https://spatie.be/github-ad-image/laravel-ai-evals.jpg" width="419px" />](https://spatie.be/github-ad-click/laravel-ai-evals)

We invest a lot of resources into creating [best in class open source packages](https://spatie.be/open-source). You can support us by [buying one of our paid products](https://spatie.be/open-source/support-us).

We highly appreciate you sending us a postcard from your hometown, mentioning which of our package(s) you are using. You'll find our address on [our contact page](https://spatie.be/about-us). We publish all received postcards on [our virtual postcard wall](https://spatie.be/open-source/postcards).

## Installation

You can install the package via composer:

```bash
composer require spatie/laravel-ai-evals --dev
```

The package registers itself as a Pest plugin. Evals call a real model, so they are slow, cost money, and are not deterministic. Without the `--evals` flag, Pest skips them with "Eval skipped. Run with [--evals] to evaluate against a real model.", so they can live next to your other tests.

Keep them in their own directory, for example `tests/Evals`, and give that directory a test case in `tests/Pest.php`:

```php
pest()->extend(Tests\TestCase::class)->use(RefreshDatabase::class)->in('Evals');
```

Make sure the test environment has the API key of your AI provider.

## Configuration

Configure the package in `tests/Pest.php`:

```php
use App\Evals\ShopJudgeAgent;
use Spatie\LaravelAiEvals\AiEvals;

AiEvals::judgeUsing(ShopJudgeAgent::class);

AiEvals::repeat(3);
```

| Method | Behaviour |
| --- | --- |
| `AiEvals::judgeUsing()` | The agent that grades the `toSatisfy()` criteria, as a class or an instance. Extend `Spatie\LaravelAiEvals\Judge\JudgeAgent` and override `instructions()` to give the judge context about your product. Defaults to `JudgeAgent`. |
| `AiEvals::repeat()` | The default number of runs per eval. Without it, every eval runs once. |

These environment variables are read when an eval runs:

| Variable | Behaviour |
| --- | --- |
| `PEST_EVALS_LARAVEL_SCORING_PROVIDER` | The provider of the judge. Defaults to your default Laravel AI provider. |
| `PEST_EVALS_LARAVEL_SCORING_MODEL` | The model of the judge. Defaults to the default model of that provider. |
| `AI_EVALS_REPEAT` | The number of runs for every eval, including evals that call `repeat()`. |

## What evals are for

These evals check your agent, not the model. They cover the parts you own: the tools, their descriptions and the data they return, the context you pass, and the format and behaviour your system prompt pushes the model towards. They do not measure what the model knows or how well it reasons.

A good rule of thumb: every check and criterion should point at something you own and could change to fix a failure. If nothing you own could fix it, drop the line.

```php
// Tests the model's reasoning. When it fails, there is nothing you can change.
->toSatisfy('Works out why the order is late.')

// Tests your agent. When it fails, you fix the tool, its description or the prompt.
->toHaveToolCalls([LookupOrderTool::class])
->toSatisfy('Bases its explanation on the shipment status that the order lookup tool returned.')
```

## Writing an eval

`evaluate()` registers a Pest test. Call `agent()` and `repeat()` first, then add one or more turns with `prompt()`. The expectations after a `prompt()` call apply to that turn.

```php
use App\Ai\Agents\SupportAgent;
use App\Ai\Tools\CancelOrderTool;
use App\Ai\Tools\LookupOrderTool;
use App\Models\Customer;
use App\Models\Order;
use Spatie\LaravelAiEvals\Drivers\AgentDriver;

evaluate('cancels an order after confirmation')
    ->agent(function () {
        $customer = Customer::factory()->create();

        Order::factory()->for($customer)->create(['number' => 'A-1001']);

        return new AgentDriver(SupportAgent::class, as: $customer);
    })
    ->repeat(3)
    ->prompt('Please cancel order A-1001.')
    ->toHaveToolCalls([LookupOrderTool::class])
    ->not->toHaveToolCalls([CancelOrderTool::class])
    ->toSatisfy('Asks the customer to confirm before cancelling.')
    ->prompt('Yes, cancel it.')
    ->approveAll()
    ->toHaveToolCalls([CancelOrderTool::class => ['order' => 'A-1001']])
    ->toHaveMaxWords(60)
    ->toSatisfy('Confirms that order A-1001 was cancelled.')
    ->toSatisfy('Does not claim a refund was issued.');
```

| Method | Behaviour |
| --- | --- |
| `agent()` | The agent to talk to: a driver, a Laravel AI agent instance or class, or a closure that sets up the test data and returns one of those. |
| `repeat()` | The number of fresh conversations. Every one of them must pass. Defaults to `AiEvals::repeat()`. |
| `prompt()` | Starts a turn with this message. Pass a closure that receives the driver when the message depends on the test data. |
| `toHaveToolCalls()` | Tools that must be called in the turn, by tool class or name, optionally with their arguments. |
| `toHaveMaxToolCalls()` | The maximum number of tool calls in the turn. |
| `toHaveMaxWords()` | The maximum number of words in the answer of the turn. |
| `toContain()` | Values the answer must contain, such as an order number or a link. |
| `toContainInOrder()` | Values the answer must contain in this order. |
| `toSatisfy()` | One criterion for the LLM judge. Call it once per criterion. |
| `toPassCheck()` | A check written in code, with a label. See [checking with code](#checking-with-code). |
| `approveAll()` | Approves every tool call that waits for approval in this turn. Without it, they are rejected. |
| `not` | Negates the next `toContain()` or `toHaveToolCalls()`. Any other method after `not` throws an exception. |

A few details of the checks:

- Tool checks count every tool call of the turn, including the calls after an approval.
- A tool class is matched by its `name()`, or by its class name when it has no public `name()` method. Tool names are resolved before the first run, so a tool class the container can't build fails the eval right away.
- Words are tokens with at least one letter or digit, so Markdown bullets do not count.
- `toContain()` matches anywhere in the answer of the turn, in any letter case, so `shipment group` also matches "Shipment groups". The failure of `->not->toContain()` quotes the text it found.
- For `toContainInOrder()`, each value must come after the value before it.
- Write criteria as concrete, checkable statements. A vague criterion gives noisy results.
- The judge grades all criteria of a turn in one call. For a later turn, it also gets the earlier turns of the run (their prompts, tool calls without results, and answers) as context, so a criterion can refer to "those orders", but only the current turn is graded.

### Tool calls with arguments

`toHaveToolCalls()` takes a list of tools, or, like Pest evals, tools with the arguments they must be called with:

```php
->toHaveToolCalls([LookupOrderTool::class])
->toHaveToolCalls([LookupOrderTool::class => ['order' => 'A-1001']])
->toHaveToolCalls(['lookup_order' => fn (array $arguments) => str_starts_with($arguments['order'], 'A-')])
->not->toHaveToolCalls([CancelOrderTool::class])
```

An argument array matches when the call has at least these arguments with the same values, compared strictly. Nested arrays are compared the same way. A closure receives the arguments of each call to that tool and matches when it returns true.

### Test data

Set up the test data in the `agent()` closure, with factories, a seeder, or anything else, and return the driver. The closure runs once per eval, before the runs. A `prompt()` closure receives the driver, by its class or as `$driver`, so a driver that keeps what was created can pass it to the prompts.

For data that is large or shared between many evals, give your driver a named constructor that runs a seeder and keeps it:

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

evaluate('summarizes an order')
    ->agent(fn () => ShopDriver::seeded())
    ->prompt(fn (ShopDriver $driver) => "What is in order {$driver->seeder->order->number}?")
    ->toSatisfy('Lists the products of the order.');
```

Each run is wrapped in a transaction on the default database connection that is rolled back afterwards, so changes made by one run do not leak into the next. Changes on other connections are not rolled back. The data from the `agent()` closure is created once, before the runs.

### Checking known values

The arguments of `toHaveToolCalls()`, `toHaveMaxToolCalls()`, `toContain()`, `toContainInOrder()` and `toSatisfy()` can be closures too. Like a `prompt()` closure, they receive the driver and return the value, or an array of values. They run once per eval, after the `agent()` closure and before the runs. This lets an eval check the answer against the data it created, not only against what the tools returned:

```php
evaluate('links to the order it describes')
    ->agent(fn () => ShopDriver::seeded())
    ->prompt(fn (ShopDriver $driver) => "Where is order {$driver->seeder->order->number}?")
    ->toContain(fn (ShopDriver $driver) => route('orders.show', $driver->seeder->order))
    ->toSatisfy(fn (ShopDriver $driver) => "Says that the order is {$driver->seeder->order->status}.");
```

Prefer `toContain()` over a criterion for exact values the agent must output, such as links to records, IDs or counts. It costs nothing, does not depend on the judge, and its failure names the missing value. It ignores letter case, so when case matters, for example in a token, check the value with [`toPassCheck()`](#checking-with-code). Use a criterion when the wording can vary.

`->not->toContain()` does the same for words the answer must avoid, such as a term your product no longer uses:

```php
evaluate('uses the current terminology')
    ->agent(fn () => ShopDriver::seeded())
    ->prompt('Which of my orders need attention?')
    ->not->toContain('shipment group', 'parcel set');
```

### Checking with code

When a check needs more than the answer, such as the rows a tool changed, write it in code with `toPassCheck()`. Give it a label and a closure:

```php
use App\Ai\Tools\LookupOrderTool;
use Spatie\LaravelAiEvals\Transcript\Transcript;

evaluate('cancels only the late orders')
    ->agent(fn () => ShopDriver::seeded())
    ->prompt('Cancel all my late orders.')
    ->approveAll()
    ->toPassCheck('Cancels exactly the late orders', function (ShopDriver $driver) {
        $cancelled = $driver->seeder->customer
            ->orders()
            ->where('status', 'cancelled')
            ->pluck('number')
            ->all();

        return $cancelled === ['A-1', 'A-3'] ?: 'Cancelled '.implode(', ', $cancelled).'.';
    })
    ->toPassCheck('Looks up each order once', function (Transcript $transcript) {
        $orders = array_column(array_column($transcript->callsTo(LookupOrderTool::class), 'arguments'), 'order');

        return count($orders) === count(array_unique($orders));
    });
```

The closure receives the driver and the `Transcript` of the turn, by their class or as `$driver` and `$transcript`. The transcript has the prompt, the tool calls with their arguments, result, status and approval (`$transcript->toolCalls`), and the answer (`$transcript->answer()`). `$transcript->callsTo()` gives the calls to one tool, by its tool class or name, optionally filtered by arguments like `toHaveToolCalls()`. Return `true` or a passing `expect()` to pass, `false` to fail, or a string to fail with that string as the reason. When the closure throws an exception, for example a failed `expect()`, the check fails with the class and message of the exception. The run goes on.

Unlike the other closures, this one runs in every run, right after the turn and before the database transaction of the run is rolled back. It sees the rows the run created or changed. It runs in a nested transaction, so a failed query does not break the rest of the run.

## Agents and drivers

`agent()` accepts a Laravel AI agent instance or class, and starts a new conversation with it for every run. For more control, use `AgentDriver` with the agent class, or a closure that creates the agent, and the participant of the conversation:

```php
new AgentDriver(SupportAgent::class, as: $customer);

new AgentDriver(fn () => new SupportAgent(locale: 'nl'), as: $customer);
```

For evals with more than one turn, or with tool approvals, the agent must remember conversations with the `RemembersConversations` trait, and the conversation needs a participant: pass `as` to the `AgentDriver`, or pass an agent instance that you called `forUser()` on. When the agent can't remember the conversation, the second turn crashes the run with an error that says so, instead of quietly starting a new conversation.

When your app talks to the agent in its own way, for example through an action that checks usage limits and adds page context, write a driver that uses that code path:

```php
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Responses\AgentResponse;
use Spatie\LaravelAiEvals\Drivers\Driver;

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

## Running evals

```bash
vendor/bin/pest --evals tests/Evals
vendor/bin/pest --evals --filter="cancels an order"
```

If you also use Pest evals, the same `--evals` flag runs both. To save time and money while you work on an eval, run it once with `AI_EVALS_REPEAT`:

```bash
AI_EVALS_REPEAT=1 vendor/bin/pest --evals --filter="cancels an order"
```

When a run fails, the eval fails with the failed turn of that run: the prompt, the tool calls with their arguments and status, the answer, and each failed check with its reason. For a criterion, the reason is the reasoning of the judge.

```
1/2 runs passed.

Run 2, turn 1 failed
  Prompt:
    Where is order 1234?
  Tool calls:
    - lookup_order {"order":"1234"} (completed)
  Answer:
    Your order shipped yesterday after a long delay in the warehouse.
  Failed checks:
    ✗ Does not blame anyone.
      The answer blames the warehouse for the delay.
```

The status of a tool call is `completed`, `failed`, `rejected`, `pending` when it still waits for approval, or `no result` when no result came back for it. An approved or rejected call also shows that decision. Long tool arguments are truncated, the answer is not.

When a run throws an exception, the run fails and the turn shows the crash, together with the tool calls and answer it got before the crash.

When the judge itself fails, for example on a rate limit or when it does not return one valid verdict for each criterion, it is not held against the agent. A run that passed all its other checks but could not be judged is not counted. A run that failed a check still counts as failed. When no run could be judged, the eval fails.

### Saving costs locally

The agent and the judge use your normal Laravel AI provider config, so a provider `url` override, for example to a local proxy, also applies to evals.

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
