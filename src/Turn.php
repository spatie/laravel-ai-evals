<?php

namespace Spatie\LaravelAiEvals;

use Closure;
use Illuminate\Support\Arr;
use LogicException;
use Spatie\LaravelAiEvals\Checks\CallsToolCheck;
use Spatie\LaravelAiEvals\Checks\Check;
use Spatie\LaravelAiEvals\Checks\ClosureCheck;
use Spatie\LaravelAiEvals\Checks\ContainsCheck;
use Spatie\LaravelAiEvals\Checks\ContainsInOrderCheck;
use Spatie\LaravelAiEvals\Checks\DoesNotCallToolCheck;
use Spatie\LaravelAiEvals\Checks\DoesNotContainCheck;
use Spatie\LaravelAiEvals\Checks\Expectation;
use Spatie\LaravelAiEvals\Checks\JudgeCriterion;
use Spatie\LaravelAiEvals\Checks\MaxToolCallsCheck;
use Spatie\LaravelAiEvals\Checks\MaxWordsCheck;
use Spatie\LaravelAiEvals\Transcript\Transcript;

/** @property-read Turn $not */
class Turn
{
    public bool $approvesAll = false;

    protected bool $negated = false;

    /** @var array<int, Closure(Closure(mixed, array<string, mixed>=): mixed): array<int, Expectation>> */
    protected array $expectationFactories = [];

    public function __construct(
        protected Evaluation $evaluation,
        public string|Closure $prompt,
    ) {}

    public function __get(string $name): static
    {
        if ($name !== 'not') {
            throw new LogicException('Undefined property '.static::class."::\${$name}.");
        }

        if ($this->negated) {
            throw new LogicException('->not is already negating the next expectation.');
        }

        $this->negated = true;

        return $this;
    }

    /** @param array<int|string, mixed>|Closure $tools */
    public function toHaveToolCalls(array|Closure $tools): static
    {
        $negated = $this->takeNegation();

        $this->expectationFactories[] = fn (Closure $resolve): array => $this->toolChecks($resolve($tools), $negated);

        return $this;
    }

    public function toHaveMaxToolCalls(int|Closure $maxCalls): static
    {
        $this->ensureNotNegated('toHaveMaxToolCalls');

        $this->expectationFactories[] = function (Closure $resolve) use ($maxCalls): array {
            $maxCalls = $resolve($maxCalls);

            if (! is_int($maxCalls)) {
                throw new LogicException('The maximum number of tool calls given to toHaveMaxToolCalls() must be an integer, but is '.get_debug_type($maxCalls).'.');
            }

            return [new MaxToolCallsCheck($maxCalls)];
        };

        return $this;
    }

    public function toHaveMaxWords(int $maxWords): static
    {
        $this->ensureNotNegated('toHaveMaxWords');

        $this->expectationFactories[] = fn (): array => [new MaxWordsCheck($maxWords)];

        return $this;
    }

    public function toContain(string|Closure ...$needles): static
    {
        $negated = $this->takeNegation();

        $this->expectationFactories[] = fn (Closure $resolve): array => array_map(
            fn (string $needle): Check => $negated ? new DoesNotContainCheck($needle) : new ContainsCheck($needle),
            $this->resolveStrings($resolve, $needles, 'toContain()'),
        );

        return $this;
    }

    public function toContainInOrder(string|Closure ...$needles): static
    {
        $this->ensureNotNegated('toContainInOrder');

        $this->expectationFactories[] = fn (Closure $resolve): array => [
            new ContainsInOrderCheck($this->resolveStrings($resolve, $needles, 'toContainInOrder()')),
        ];

        return $this;
    }

    public function toSatisfy(string|Closure $criterion): static
    {
        $this->ensureNotNegated('toSatisfy');

        $this->expectationFactories[] = fn (Closure $resolve): array => array_map(
            fn (string $criterion): JudgeCriterion => new JudgeCriterion($criterion),
            $this->resolveStrings($resolve, [$criterion], 'toSatisfy()'),
        );

        return $this;
    }

    public function toPassCheck(string $label, Closure $check): static
    {
        $this->ensureNotNegated('toPassCheck');

        $this->expectationFactories[] = fn (Closure $resolve): array => [
            new ClosureCheck(
                $label,
                fn (Transcript $transcript): mixed => $resolve($check, [Transcript::class => $transcript, 'transcript' => $transcript]),
            ),
        ];

        return $this;
    }

    public function approveAll(): static
    {
        $this->ensureNotNegated('approveAll');

        $this->approvesAll = true;

        return $this;
    }

    public function prompt(string|Closure $prompt): Turn
    {
        $this->ensureNotNegated('prompt');

        return $this->evaluation->prompt($prompt);
    }

    /**
     * @param  Closure(mixed, array<string, mixed>=): mixed  $resolve
     * @return array<int, Expectation>
     */
    public function resolveExpectations(Closure $resolve): array
    {
        if ($this->negated) {
            throw new LogicException('->not must be followed by toContain() or toHaveToolCalls().');
        }

        return array_merge(...array_map(
            fn (Closure $factory): array => $factory($resolve),
            $this->expectationFactories,
        ));
    }

    /** @return array<int, Check> */
    protected function toolChecks(mixed $tools, bool $negated): array
    {
        if ($tools === null) {
            throw new LogicException('A closure given to toHaveToolCalls() returned null.');
        }

        $checks = [];

        foreach (Arr::wrap($tools) as $key => $value) {
            [$tool, $arguments] = is_int($key) ? [$value, null] : [$key, $value];

            $this->ensureToolCall($tool, $arguments);

            $checks[] = $negated
                ? new DoesNotCallToolCheck($tool, $arguments)
                : new CallsToolCheck($tool, $arguments);
        }

        return $checks;
    }

    protected function takeNegation(): bool
    {
        $negated = $this->negated;

        $this->negated = false;

        return $negated;
    }

    protected function ensureNotNegated(string $method): void
    {
        if ($this->negated) {
            throw new LogicException("->not can't be used with {$method}(). It only works with toContain() and toHaveToolCalls().");
        }
    }

    protected function ensureToolCall(mixed $tool, mixed $arguments): void
    {
        if (! is_string($tool) || $tool === '') {
            throw new LogicException('The tools given to toHaveToolCalls() must be tool names or classes, but one is '.get_debug_type($tool).'.');
        }

        if ($arguments !== null && ! is_array($arguments) && ! $arguments instanceof Closure) {
            throw new LogicException("The expected arguments of {$tool} given to toHaveToolCalls() must be an array or a closure, but are ".get_debug_type($arguments).'.');
        }
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
