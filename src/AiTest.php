<?php

namespace Spatie\LaravelAiTests;

use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Assert;
use Spatie\LaravelAiTests\Drivers\Driver;
use Spatie\LaravelAiTests\Results\ResultWriter;

class AiTest
{
    /** @var class-string<Seeder>|null */
    public ?string $seederClass = null;

    public ?Closure $setUp = null;

    public Closure|Driver|null $driver = null;

    public ?int $runs = null;

    public ?float $minPassRate = null;

    /** @var array<int, Turn> */
    public array $turns = [];

    public function __construct(
        public string $description,
        public string $testFile,
    ) {}

    /** @param class-string<Seeder> $seederClass */
    public function seed(string $seederClass): static
    {
        $this->seederClass = $seederClass;

        return $this;
    }

    public function setUp(Closure $setUp): static
    {
        $this->setUp = $setUp;

        return $this;
    }

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

    public function ensureRunnable(): void
    {
        if ($this->driver === null) {
            throw new LogicException("The AI test \"{$this->description}\" has no driver. Call ->driver().");
        }

        if ($this->turns === []) {
            throw new LogicException("The AI test \"{$this->description}\" has no turns. Call ->user().");
        }
    }

    public function execute(): void
    {
        $result = app(Runner::class)->run($this);

        $resultPath = app(ResultWriter::class)->write($result);

        $runCount = count($result->runs);

        Assert::assertTrue(
            $result->passed(),
            "{$result->passedRuns()}/{$runCount} runs passed, below the minimum pass rate of {$result->minPassRate}. See {$resultPath}.",
        );
    }
}
