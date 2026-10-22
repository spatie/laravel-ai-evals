<?php

namespace Spatie\LaravelAiTests\Results;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Checks\Expectation;
use Spatie\LaravelAiTests\Transcript\ToolCallRecord;
use Spatie\LaravelAiTests\Transcript\Transcript;
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

        $temporaryPath = $path.'.'.Str::random(8).'.tmp';

        File::put($temporaryPath, $this->render($result));

        File::move($temporaryPath, $path);

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
        $facts = Arr::whereNotNull([
            'slug' => $result->aiTest->slug(),
            'title' => $result->aiTest->title(),
            'test_file' => Str::after($result->aiTest->testFile, base_path().DIRECTORY_SEPARATOR),
            'model' => $result->model(),
            'judge_model' => $result->judgeModel(),
            'context' => $result->context !== [] ? $result->context : null,
            'runs' => count($result->runs),
            'passed_runs' => $result->passedRuns(),
            'unjudged_runs' => $result->unjudgedRuns() > 0 ? $result->unjudgedRuns() : null,
            'pass_rate' => round($result->passRate(), 2),
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
            'checks' => $this->checks($result, $index),
            'runs' => array_map(fn (RunResult $run): array => $this->run($run, $index), $result->runs),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function checks(TestResult $result, int $index): array
    {
        return array_map(function (Expectation $expectation) use ($result, $index): array {
            $runs = [];

            foreach ($result->runs as $run) {
                $turnResult = $run->turns[$index] ?? null;
                $outcome = $turnResult?->outcomeFor($expectation);

                if ($outcome === null && $turnResult?->judgeError !== null) {
                    continue;
                }

                $runs[$run->number] = $outcome->passed ?? false;
            }

            return [
                'label' => $expectation->label(),
                'kind' => $expectation->kind(),
                'passed_runs' => count(array_filter($runs)),
                'runs' => $runs,
            ];
        }, $result->expectations[$index]);
    }

    /** @return array<string, mixed> */
    protected function run(RunResult $run, int $index): array
    {
        $turnResult = $run->turns[$index] ?? null;

        if ($turnResult === null && count($run->turns) === $index) {
            return $this->crashedRun($run);
        }

        if ($turnResult === null) {
            return ['run' => $run->number, 'passed' => false, 'not_reached' => true];
        }

        $verdicts = $this->verdicts($turnResult);

        return Arr::whereNotNull([
            'run' => $run->number,
            'passed' => $turnResult->passed(),
            'judge_error' => $turnResult->judgeError !== null ? $this->error($turnResult->judgeError) : null,
            ...$this->transcript($turnResult->transcript),
            'verdicts' => $verdicts !== [] ? $verdicts : null,
        ]);
    }

    /** @return array<string, mixed> */
    protected function crashedRun(RunResult $run): array
    {
        $transcript = $run->crashedTurn !== null ? $this->transcript($run->crashedTurn) : [];

        return Arr::whereNotNull([
            'run' => $run->number,
            'passed' => false,
            'crash' => $this->error((string) $run->crash),
            ...array_filter($transcript),
        ]);
    }

    /** @return array{tool_calls: array<int, array<string, mixed>>, answer: string} */
    protected function transcript(Transcript $transcript): array
    {
        return [
            'tool_calls' => array_values(array_map(
                fn (ToolCallRecord $toolCall): array => $this->toolCall($toolCall),
                $transcript->toolCalls,
            )),
            'answer' => $this->normalizeLineEndings($transcript->answer()),
        ];
    }

    protected function error(string $error): string
    {
        return (string) Str::of($error)->squish()->limit(500);
    }

    /** @return array<int, array<string, mixed>> */
    protected function verdicts(TurnResult $turnResult): array
    {
        $reportedOutcomes = array_filter(
            $turnResult->outcomes,
            fn (CheckOutcome $outcome): bool => $outcome->judged() || ! $outcome->passed,
        );

        return array_values(array_map(
            fn (CheckOutcome $outcome): array => Arr::whereNotNull([
                'check' => $outcome->label(),
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
