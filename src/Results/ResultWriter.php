<?php

namespace Spatie\LaravelAiTests\Results;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Checks\Check;
use Spatie\LaravelAiTests\Transcript\ToolCallRecord;
use Spatie\LaravelAiTests\Turn;
use Symfony\Component\Yaml\Yaml;

class ResultWriter
{
    protected const InlineLevel = 20;

    protected const Indentation = 2;

    public function write(TestResult $result): string
    {
        $path = config('ai-tests.results_path')."/{$result->aiTest->slug()}.yaml";

        File::ensureDirectoryExists(dirname($path));

        File::put($path, $this->render($result));

        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }

    public function render(TestResult $result): string
    {
        $yaml = Yaml::dump(
            $this->data($result),
            static::InlineLevel,
            static::Indentation,
            Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_COMPACT_NESTED_MAPPING | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE,
        );

        return str_ends_with($yaml, "\n") ? $yaml : "{$yaml}\n";
    }

    /** @return array<string, mixed> */
    protected function data(TestResult $result): array
    {
        $seederClass = $result->aiTest->seederClass;

        $facts = Arr::whereNotNull([
            'slug' => $result->aiTest->slug(),
            'title' => $result->aiTest->title(),
            'test_file' => Str::after($result->aiTest->testFile, base_path().DIRECTORY_SEPARATOR),
            'seeder' => $seederClass !== null ? class_basename($seederClass) : null,
            'model' => $result->model(),
            'judge_model' => $result->judgeModel(),
            'runs' => count($result->runs),
            'passed_runs' => $result->passedRuns(),
            'pass_rate' => $result->passRate(),
            'min_pass_rate' => $result->minPassRate,
            'passed' => $result->passed(),
            'ran_at' => $result->ranAt->toIso8601String(),
        ]);

        $turns = array_map(
            fn (Turn $turn, int $index): array => $this->turn($result, $turn, $index),
            $result->aiTest->turns,
            array_keys($result->aiTest->turns),
        );

        return [...$facts, 'turns' => $turns];
    }

    /** @return array<string, mixed> */
    protected function turn(TestResult $result, Turn $turn, int $index): array
    {
        return [
            'prompt' => $this->normalizeLineEndings($result->prompts[$index]),
            'checks' => $this->checks($result, $turn, $index),
            'runs' => array_map(fn (RunResult $run): array => $this->run($run, $index), $result->runs),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function checks(TestResult $result, Turn $turn, int $index): array
    {
        $checks = [
            ...array_map(fn (Check $check): array => ['label' => $check->label(), 'kind' => $check->kind()], $turn->checks),
            ...array_map(fn (string $criterion): array => ['label' => $criterion, 'kind' => 'rubric'], $turn->rubric),
        ];

        return array_map(function (array $check, int $checkIndex) use ($result, $index): array {
            $runs = [];

            foreach ($result->runs as $run) {
                $runs[$run->number] = $run->turns[$index]->outcomes[$checkIndex]->passed ?? false;
            }

            return [...$check, 'passed_runs' => count(array_filter($runs)), 'runs' => $runs];
        }, $checks, array_keys($checks));
    }

    /** @return array<string, mixed> */
    protected function run(RunResult $run, int $index): array
    {
        $turnResult = $run->turns[$index] ?? null;

        if ($turnResult === null && count($run->turns) === $index) {
            return ['run' => $run->number, 'passed' => false, 'crash' => (string) Str::of((string) $run->crash)->squish()->limit(500)];
        }

        if ($turnResult === null) {
            return ['run' => $run->number, 'passed' => false, 'not_reached' => true];
        }

        $verdicts = $this->verdicts($turnResult);

        return Arr::whereNotNull([
            'run' => $run->number,
            'passed' => $turnResult->passed(),
            'tool_calls' => array_values(array_map(
                fn (ToolCallRecord $toolCall): array => $this->toolCall($toolCall),
                $turnResult->transcript->toolCalls,
            )),
            'answer' => $this->normalizeLineEndings($turnResult->transcript->answer()),
            'verdicts' => $verdicts !== [] ? $verdicts : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function verdicts(TurnResult $turnResult): array
    {
        $reportedOutcomes = array_filter(
            $turnResult->outcomes,
            fn (CheckOutcome $outcome): bool => $outcome->judged || ! $outcome->passed,
        );

        return array_values(array_map(
            fn (CheckOutcome $outcome): array => Arr::whereNotNull([
                'check' => $outcome->label,
                'passed' => $outcome->passed,
                'reason' => $outcome->reason,
            ]),
            $reportedOutcomes,
        ));
    }

    /** @return array<string, mixed> */
    protected function toolCall(ToolCallRecord $toolCall): array
    {
        return Arr::whereNotNull([
            'name' => $toolCall->name,
            'arguments' => $toolCall->arguments !== [] ? $toolCall->arguments : null,
            'status' => $toolCall->status->value,
            'approval' => $toolCall->decisionLabel(),
        ]);
    }

    protected function normalizeLineEndings(string $text): string
    {
        return str_replace(["\r\n", "\r"], "\n", $text);
    }
}
