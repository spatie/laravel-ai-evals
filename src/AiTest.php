<?php

namespace Spatie\LaravelAiTests;

use Closure;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Assert;
use Spatie\LaravelAiTests\Drivers\Driver;
use Spatie\LaravelAiTests\Results\ResultWriter;
use Spatie\LaravelAiTests\Results\TestResult;
use Throwable;

class AiTest
{
    public Closure|Driver|null $driver = null;

    public ?int $runs = null;

    public ?float $minPassRate = null;

    /** @var array<int, Turn> */
    public array $turns = [];

    public function __construct(
        public string $description,
        public string $testFile,
    ) {}

    public function driver(Closure|Driver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    public function runs(int $runs): static
    {
        $this->runs = $runs;

        return $this;
    }

    public function minPassRate(float $minPassRate): static
    {
        $this->minPassRate = $minPassRate;

        return $this;
    }

    public function user(string|Closure $prompt): Turn
    {
        $turn = new Turn($this, $prompt);

        $this->turns[] = $turn;

        return $turn;
    }

    public function slug(): string
    {
        return Str::slug($this->description);
    }

    public function title(): string
    {
        return Str::ucfirst($this->description);
    }

    public function runCount(): int
    {
        return (int) ($this->runs ?? config('ai-tests.runs'));
    }

    public function requiredPassRate(): float
    {
        return (float) ($this->minPassRate ?? config('ai-tests.min_pass_rate'));
    }

    public function ensureRunnable(): void
    {
        if ($this->driver === null) {
            throw new LogicException("The AI test \"{$this->description}\" has no driver. Call ->driver().");
        }

        if ($this->turns === []) {
            throw new LogicException("The AI test \"{$this->description}\" has no turns. Call ->user().");
        }

        if ($this->runCount() < 1) {
            throw new LogicException("The AI test \"{$this->description}\" needs at least one run, but has {$this->runCount()}.");
        }

        if ($this->requiredPassRate() < 0 || $this->requiredPassRate() > 1) {
            throw new LogicException("The minimum pass rate of the AI test \"{$this->description}\" must be between 0 and 1, but is {$this->requiredPassRate()}.");
        }

        $this->ensureChecksCanBeLabelled();
    }

    protected function ensureChecksCanBeLabelled(): void
    {
        foreach ($this->turns as $turn) {
            foreach ($turn->expectations() as $expectation) {
                try {
                    $expectation->label();
                } catch (Throwable $exception) {
                    throw new LogicException("The AI test \"{$this->description}\" has a check that can't be set up. {$exception->getMessage()}", previous: $exception);
                }
            }
        }
    }

    public function execute(): void
    {
        $result = app(Runner::class)->run($this);

        $resultPath = app(ResultWriter::class)->write($result);

        Assert::assertTrue($result->passed(), $this->failureMessage($result, $resultPath));
    }

    protected function failureMessage(TestResult $result, string $resultPath): string
    {
        $summary = $result->judgedRuns() === 0
            ? 'No run could be judged, because the judge failed in every run.'
            : "{$result->passedRuns()}/{$result->judgedRuns()} runs passed, below the minimum pass rate of {$result->minPassRate}.";

        if ($result->judgedRuns() > 0 && $result->unjudgedRuns() > 0) {
            $summary .= " {$result->unjudgedRuns()} unjudged ".Str::plural('run', $result->unjudgedRuns()).' left out.';
        }

        return implode("\n", ["{$summary} See {$resultPath}.", ...$result->errors()]);
    }
}
