<?php

namespace Spatie\LaravelAiTests;

use Closure;
use Illuminate\Support\Arr;
use LogicException;
use Spatie\LaravelAiTests\Checks\CallsToolCheck;
use Spatie\LaravelAiTests\Checks\Check;
use Spatie\LaravelAiTests\Checks\DoesNotCallToolCheck;
use Spatie\LaravelAiTests\Checks\DoesNotMentionCheck;
use Spatie\LaravelAiTests\Checks\Expectation;
use Spatie\LaravelAiTests\Checks\MaxToolCallsCheck;
use Spatie\LaravelAiTests\Checks\MaxWordsCheck;
use Spatie\LaravelAiTests\Checks\MentionsCheck;
use Spatie\LaravelAiTests\Checks\MentionsInOrderCheck;
use Spatie\LaravelAiTests\Checks\RubricCriterion;

class Turn
{
    public bool $approvesAll = false;

    /** @var array<int, Closure(Closure(mixed): mixed): array<int, Expectation>> */
    protected array $expectationFactories = [];

    public function __construct(
        protected AiTest $aiTest,
        public string|Closure $prompt,
    ) {}

    /**
     * @param  array<int, string>|Closure  $called
     * @param  array<int, string>|Closure  $notCalled
     */
    public function expectTools(
        array|Closure $called = [],
        array|Closure $notCalled = [],
        int|Closure|null $maxCalls = null,
    ): static {
        $this->expectationFactories[] = function (Closure $resolve) use ($called, $notCalled, $maxCalls): array {
            $checks = [
                ...array_map(
                    fn (string $tool): Check => new CallsToolCheck($tool),
                    $this->strings($resolve($called), 'expectTools()'),
                ),
                ...array_map(
                    fn (string $tool): Check => new DoesNotCallToolCheck($tool),
                    $this->strings($resolve($notCalled), 'expectTools()'),
                ),
            ];

            $maxCalls = $resolve($maxCalls);

            if ($maxCalls !== null && ! is_int($maxCalls)) {
                throw new LogicException('The maximum number of tool calls given to expectTools() must be an integer, but is '.get_debug_type($maxCalls).'.');
            }

            if ($maxCalls !== null) {
                $checks[] = new MaxToolCallsCheck($maxCalls);
            }

            return $checks;
        };

        return $this;
    }

    public function expectMaxWords(int $maxWords): static
    {
        $this->expectationFactories[] = fn (): array => [new MaxWordsCheck($maxWords)];

        return $this;
    }

    public function expectMentions(string|Closure ...$needles): static
    {
        $this->expectationFactories[] = fn (Closure $resolve): array => array_map(
            fn (string $needle): Check => new MentionsCheck($needle),
            $this->resolveStrings($resolve, $needles, 'expectMentions()'),
        );

        return $this;
    }

    public function expectMentionsInOrder(string|Closure ...$needles): static
    {
        $this->expectationFactories[] = fn (Closure $resolve): array => [
            new MentionsInOrderCheck($this->resolveStrings($resolve, $needles, 'expectMentionsInOrder()')),
        ];

        return $this;
    }

    public function expectNotMentions(string|Closure ...$needles): static
    {
        $this->expectationFactories[] = fn (Closure $resolve): array => array_map(
            fn (string $needle): Check => new DoesNotMentionCheck($needle),
            $this->resolveStrings($resolve, $needles, 'expectNotMentions()'),
        );

        return $this;
    }

    public function expectRubric(string|Closure ...$criteria): static
    {
        $this->expectationFactories[] = fn (Closure $resolve): array => array_map(
            fn (string $criterion): RubricCriterion => new RubricCriterion($criterion),
            $this->resolveStrings($resolve, $criteria, 'expectRubric()'),
        );

        return $this;
    }

    /**
     * @param  Closure(mixed): mixed  $resolve
     * @return array<int, Expectation>
     */
    public function resolveExpectations(Closure $resolve): array
    {
        $expectations = array_merge(...array_map(
            fn (Closure $factory): array => $factory($resolve),
            $this->expectationFactories,
        ));

        return [
            ...array_filter($expectations, fn (Expectation $expectation): bool => $expectation instanceof Check),
            ...array_filter($expectations, fn (Expectation $expectation): bool => $expectation instanceof RubricCriterion),
        ];
    }

    public function approveAll(): static
    {
        $this->approvesAll = true;

        return $this;
    }

    public function user(string|Closure $prompt): Turn
    {
        return $this->aiTest->user($prompt);
    }

    /**
     * @param  Closure(mixed): mixed  $resolve
     * @param  array<int, string|Closure>  $values
     * @return array<int, string>
     */
    protected function resolveStrings(Closure $resolve, array $values, string $method): array
    {
        return array_merge(...array_map(
            fn (string|Closure $value): array => $this->strings($resolve($value), $method),
            $values,
        ));
    }

    /** @return array<int, string> */
    protected function strings(mixed $value, string $method): array
    {
        if ($value === null) {
            throw new LogicException("A closure given to {$method} returned null.");
        }

        return array_map(function (mixed $item) use ($method): string {
            if (! is_string($item) && ! is_int($item)) {
                throw new LogicException("The values given to {$method} must be strings, but one is ".get_debug_type($item).'.');
            }

            if ($item === '') {
                throw new LogicException("The values given to {$method} must not be empty strings.");
            }

            return (string) $item;
        }, array_values(Arr::wrap($value)));
    }
}
