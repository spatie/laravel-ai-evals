<?php

namespace Spatie\LaravelAiTests\Results;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Checks\Check;
use Spatie\LaravelAiTests\Transcript\ToolCallRecord;
use Spatie\LaravelAiTests\Transcript\ToolCallStatus;
use Spatie\LaravelAiTests\Transcript\Transcript;
use Spatie\LaravelAiTests\Turn;
use Symfony\Component\Yaml\Yaml;

class ResultWriter
{
    public function write(TestResult $result): string
    {
        $path = config('ai-tests.results_path')."/{$result->aiTest->slug()}.md";

        File::ensureDirectoryExists(dirname($path));

        File::put($path, $this->render($result));

        return Str::after($path, base_path().DIRECTORY_SEPARATOR);
    }

    public function render(TestResult $result): string
    {
        $lines = [
            '---',
            rtrim(Yaml::dump($this->frontmatter($result))),
            '---',
            '',
            "# {$result->aiTest->title()}",
        ];

        foreach ($result->aiTest->turns as $index => $turn) {
            $lines = [...$lines, '', ...$this->turnSection($result, $turn, $index)];
        }

        return implode("\n", $lines)."\n";
    }

    /** @return array<string, string|int|float|bool> */
    protected function frontmatter(TestResult $result): array
    {
        $seederClass = $result->aiTest->seederClass;

        $frontmatter = [
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
        ];

        return array_filter($frontmatter, fn (mixed $value): bool => $value !== null);
    }

    /** @return array<int, string> */
    protected function turnSection(TestResult $result, Turn $turn, int $index): array
    {
        $prompt = Str::of($result->prompts[$index])->squish();

        return [
            '## Turn '.($index + 1),
            '',
            "**User:** {$prompt}",
            ...$this->checksTable($result, $turn, $index),
            ...$this->judgeNotes($result, $index),
            ...$this->crashes($result, $index),
            ...$this->exampleAnswer($result, $index),
        ];
    }

    /** @return array<int, string> */
    protected function checksTable(TestResult $result, Turn $turn, int $index): array
    {
        $labels = [
            ...array_map(fn (Check $check): string => $check->label(), $turn->checks),
            ...$turn->rubric,
        ];

        if ($labels === []) {
            return [];
        }

        $rows = ['', '| Check | Passed |', '| --- | --- |'];
        $runCount = count($result->runs);

        foreach ($labels as $checkIndex => $label) {
            $passedCount = count(array_filter(
                $result->runs,
                fn (RunResult $run): bool => $run->turns[$index]->outcomes[$checkIndex]->passed ?? false,
            ));

            $rows[] = '| '.str_replace('|', '\|', $label)." | {$passedCount}/{$runCount} |";
        }

        return $rows;
    }

    /** @return array<int, string> */
    protected function judgeNotes(TestResult $result, int $index): array
    {
        $notes = [];

        foreach ($result->runs as $run) {
            foreach ($run->turns[$index]->outcomes ?? [] as $outcome) {
                if ($outcome->judged && ! $outcome->passed) {
                    $notes[] = "- Run {$run->number}, \"".Str::limit($outcome->label, 40)."\": failed. {$outcome->reason}";
                }
            }
        }

        if ($notes === []) {
            return [];
        }

        return ['', '**Judge notes**', '', ...$notes];
    }

    /** @return array<int, string> */
    protected function crashes(TestResult $result, int $index): array
    {
        $crashes = [];

        foreach ($result->runs as $run) {
            if ($run->crash !== null && count($run->turns) === $index) {
                $crashes[] = "- Run {$run->number}: ".Str::of($run->crash)->squish()->limit(500);
            }
        }

        if ($crashes === []) {
            return [];
        }

        return ['', '**Crashes**', '', ...$crashes];
    }

    /** @return array<int, string> */
    protected function exampleAnswer(TestResult $result, int $index): array
    {
        $run = $result->exampleRun();
        $turnResult = $run->turns[$index] ?? null;

        if ($turnResult === null) {
            return ['', "**Example answer** (run {$run->number})", '', '_This run crashed before it finished this turn._'];
        }

        $status = $turnResult->passed() ? 'passed' : 'failed';
        $answer = $turnResult->transcript->answer();

        return [
            '',
            "**Example answer** (run {$run->number}, {$status})",
            '',
            'Tools: '.$this->toolList($turnResult->transcript),
            '',
            $answer !== '' ? $answer : '_No answer text._',
        ];
    }

    protected function toolList(Transcript $transcript): string
    {
        if ($transcript->toolCalls === []) {
            return 'none';
        }

        return collect($transcript->toolCalls)
            ->map(fn (ToolCallRecord $toolCall): string => $this->toolDescription($toolCall))
            ->implode(', ');
    }

    protected function toolDescription(ToolCallRecord $toolCall): string
    {
        $details = array_filter([
            $toolCall->argumentList(),
            $toolCall->decisionLabel(),
            $toolCall->status === ToolCallStatus::Failed ? 'failed' : null,
        ]);

        if ($details === []) {
            return "`{$toolCall->name}`";
        }

        return "`{$toolCall->name}` (".implode(', ', $details).')';
    }
}
