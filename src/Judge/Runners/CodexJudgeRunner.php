<?php

namespace Spatie\LaravelAiTests\Judge\Runners;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\LaravelAiTests\Judge\JudgeOutput;

class CodexJudgeRunner extends CliJudgeRunner
{
    protected function name(): string
    {
        return 'Codex';
    }

    protected function configKey(): string
    {
        return 'codex';
    }

    /** @param array<string, mixed> $schema */
    protected function runIn(
        string $directory,
        string $instructions,
        array $schema,
        string $prompt,
    ): JudgeOutput {
        File::put("{$directory}/instructions.md", $instructions);
        File::put("{$directory}/schema.json", (string) json_encode($schema));

        $result = Process::path($this->workspace($directory))
            ->input($prompt)
            ->timeout($this->timeout())
            ->run($this->command($directory));

        if ($result->failed()) {
            throw $this->failure($result);
        }

        if (! File::exists("{$directory}/verdicts.json")) {
            throw new RuntimeException("{$this->name()} wrote no final message.");
        }

        $verdicts = json_decode(File::get("{$directory}/verdicts.json"), true);

        $usedModel = Str::match('/^model: (\S+)$/m', $result->errorOutput());

        return new JudgeOutput(
            $this->verdicts($verdicts),
            $this->modelLabel($usedModel !== '' ? $usedModel : $this->model()),
        );
    }

    /** @return array<int, string> */
    protected function command(string $directory): array
    {
        $model = $this->model();

        return [
            $this->binary(),
            'exec',
            '--ephemeral',
            '--skip-git-repo-check',
            '--ignore-user-config',
            '--ignore-rules',
            '--sandbox', 'read-only',
            '--cd', $this->workspace($directory),
            '--config', 'model_instructions_file='.$this->tomlString("{$directory}/instructions.md"),
            '--config', 'project_doc_max_bytes=0',
            '--config', 'skills.include_instructions=false',
            '--config', 'include_apps_instructions=false',
            '--config', 'include_environment_context=false',
            '--config', 'web_search="disabled"',
            '--disable', 'shell_tool',
            '--disable', 'unified_exec',
            '--disable', 'code_mode_host',
            '--disable', 'multi_agent',
            '--disable', 'apps',
            '--disable', 'plugins',
            '--disable', 'hooks',
            '--disable', 'image_generation',
            '--output-schema', "{$directory}/schema.json",
            '--output-last-message', "{$directory}/verdicts.json",
            ...($model !== null ? ['--model', $model] : []),
            '-',
        ];
    }

    protected function tomlString(string $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
