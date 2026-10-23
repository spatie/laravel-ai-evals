<?php

namespace Spatie\LaravelAiTests\Judge\Runners;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\ObjectSchema;
use LogicException;
use RuntimeException;
use Spatie\LaravelAiTests\Judge\JudgeOutput;

abstract class CliJudgeRunner implements JudgeRunner
{
    abstract protected function name(): string;

    abstract protected function configKey(): string;

    /** @param array<string, mixed> $schema */
    abstract protected function runIn(
        string $directory,
        string $instructions,
        array $schema,
        string $prompt,
    ): JudgeOutput;

    public function run(Agent $agent, string $prompt): JudgeOutput
    {
        if (! $agent instanceof HasStructuredOutput) {
            throw new LogicException('The judge agent '.$agent::class.' must have structured output.');
        }

        $schema = (new ObjectSchema($agent->schema(new JsonSchemaTypeFactory)))->toSchema();

        $directory = $this->createTemporaryDirectory();

        try {
            return $this->runIn($directory, (string) $agent->instructions(), $schema, $prompt);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    protected function createTemporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/ai-tests-judge-'.Str::random(16);

        File::ensureDirectoryExists($this->workspace($directory));

        return $directory;
    }

    protected function workspace(string $directory): string
    {
        return "{$directory}/workspace";
    }

    protected function binary(): string
    {
        return config("ai-tests.judge.{$this->configKey()}.binary") ?: $this->configKey();
    }

    protected function model(): ?string
    {
        return config("ai-tests.judge.{$this->configKey()}.model") ?: null;
    }

    protected function timeout(): int
    {
        return (int) (config("ai-tests.judge.{$this->configKey()}.timeout") ?: 300);
    }

    protected function failure(ProcessResult $result): RuntimeException
    {
        $output = trim($result->errorOutput()) !== '' ? $result->errorOutput() : $result->output();

        return new RuntimeException("{$this->name()} exited with code {$result->exitCode()}: {$this->lastPart(trim($output))}");
    }

    protected function lastPart(string $output): string
    {
        if (Str::length($output) <= 400) {
            return $output;
        }

        return '...'.Str::substr($output, -400);
    }

    /** @return array<int, array{criterion: int, reason: string, pass: bool}> */
    protected function verdicts(mixed $output): array
    {
        $verdicts = is_array($output) ? ($output['verdicts'] ?? null) : null;

        if (! is_array($verdicts) || ! collect($verdicts)->every(fn (mixed $verdict): bool => $this->isVerdict($verdict))) {
            $received = Str::limit((string) json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 500);

            throw new RuntimeException("{$this->name()} returned output that does not match the judge schema: {$received}");
        }

        return array_values($verdicts);
    }

    protected function isVerdict(mixed $verdict): bool
    {
        return is_array($verdict)
            && is_int($verdict['criterion'] ?? null)
            && is_string($verdict['reason'] ?? null)
            && is_bool($verdict['pass'] ?? null);
    }

    protected function modelLabel(?string $model): string
    {
        return $model !== null ? "{$model} via {$this->name()}" : $this->name();
    }
}
