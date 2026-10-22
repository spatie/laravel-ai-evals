<?php

namespace Spatie\LaravelAiTests;

use Closure;
use Spatie\LaravelAiTests\Checks\CallsToolCheck;
use Spatie\LaravelAiTests\Checks\Check;
use Spatie\LaravelAiTests\Checks\DoesNotCallToolCheck;
use Spatie\LaravelAiTests\Checks\MaxToolCallsCheck;
use Spatie\LaravelAiTests\Checks\MaxWordsCheck;

class Turn
{
    public bool $approvesAll = false;

    /** @var array<int, Check> */
    public array $checks = [];

    /** @var array<int, string> */
    public array $rubric = [];

    public function __construct(
        protected AiTest $aiTest,
        public string|Closure $prompt,
    ) {}

    /**
     * @param  array<int, string>  $called
     * @param  array<int, string>  $notCalled
     */
    public function expectTools(array $called = [], array $notCalled = [], ?int $maxCalls = null): static
    {
        foreach ($called as $tool) {
            $this->checks[] = new CallsToolCheck($tool);
        }

        foreach ($notCalled as $tool) {
            $this->checks[] = new DoesNotCallToolCheck($tool);
        }

        if ($maxCalls !== null) {
            $this->checks[] = new MaxToolCallsCheck($maxCalls);
        }

        return $this;
    }

    public function expectMaxWords(int $maxWords): static
    {
        $this->checks[] = new MaxWordsCheck($maxWords);

        return $this;
    }

    public function expectRubric(string ...$criteria): static
    {
        $this->rubric = [...$this->rubric, ...array_values($criteria)];

        return $this;
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
}
