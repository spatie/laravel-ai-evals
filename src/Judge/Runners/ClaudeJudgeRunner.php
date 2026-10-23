<?php

namespace Spatie\LaravelAiTests\Judge\Runners;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Spatie\LaravelAiTests\Judge\JudgeOutput;

class ClaudeJudgeRunner extends CliJudgeRunner
{
    protected function name(): string
    {
        return 'Claude Code';
    }

    protected function configKey(): string
    {
        return 'claude';
    }

    /** @param array<string, mixed> $schema */
    protected function runIn(
        string $directory,
        string $instructions,
        array $schema,
        string $prompt,
    ): JudgeOutput {
        $result = Process::path($this->workspace($directory))
            ->input($prompt)
            ->timeout($this->timeout())
            ->run($this->command($instructions, $schema));

        if ($result->failed()) {
            throw $this->failure($result);
        }

        $response = json_decode($result->output(), true);

        if (! is_array($response)) {
            throw new RuntimeException("{$this->name()} returned output that is not JSON.");
        }

        if (($response['is_error'] ?? false) === true) {
            throw new RuntimeException("{$this->name()} failed: ".($response['result'] ?? 'no reason given'));
        }

        return new JudgeOutput(
            $this->verdicts($response['structured_output'] ?? null),
            $this->modelLabel($this->usedModel($response) ?? $this->model()),
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    protected function command(string $instructions, array $schema): array
    {
        $model = $this->model();

        return [
            $this->binary(),
            '--print',
            '--output-format', 'json',
            '--json-schema', (string) json_encode($schema),
            '--system-prompt', $instructions,
            '--tools', '',
            '--strict-mcp-config',
            '--setting-sources', '',
            '--safe-mode',
            '--disable-slash-commands',
            '--no-session-persistence',
            ...($model !== null ? ['--model', $model] : []),
        ];
    }

    /** @param array<string, mixed> $response */
    protected function usedModel(array $response): ?string
    {
        $modelUsage = $response['modelUsage'] ?? null;

        if (! is_array($modelUsage) || $modelUsage === []) {
            return null;
        }

        return (string) collect($modelUsage)
            ->sortByDesc(fn (mixed $usage): int => is_array($usage) ? (int) ($usage['outputTokens'] ?? 0) : 0)
            ->keys()
            ->first();
    }
}
