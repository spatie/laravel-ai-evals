<?php

namespace Spatie\LaravelAiEvals\Results;

use Illuminate\Support\Str;
use Spatie\LaravelAiEvals\Transcript\ToolCallRecord;
use Spatie\LaravelAiEvals\Transcript\Transcript;

class Report
{
    public function __construct(
        protected EvaluationResult $result,
    ) {}

    public function render(): string
    {
        return collect($this->result->runs)
            ->flatMap(fn (RunResult $run, int $index): array => $this->runSections($run, $index + 1))
            ->prepend($this->summary())
            ->implode("\n\n");
    }

    protected function summary(): string
    {
        $result = $this->result;

        if ($result->judgedRuns() === 0) {
            return 'No run could be judged, because the judge failed in every run.';
        }

        $summary = "{$result->passedRuns()}/{$result->judgedRuns()} runs passed.";

        if ($result->unjudgedRuns() > 0) {
            $summary .= " {$result->unjudgedRuns()} unjudged ".Str::plural('run', $result->unjudgedRuns()).' left out.';
        }

        return $summary;
    }

    /** @return array<int, string> */
    protected function runSections(RunResult $run, int $runNumber): array
    {
        $sections = [];

        foreach ($run->turns as $index => $turn) {
            if ($turn->passed()) {
                continue;
            }

            $turnNumber = $index + 1;

            $sections[] = $this->section(
                "Run {$runNumber}, turn {$turnNumber} {$this->turnStatus($turn)}",
                $turn->transcript,
                collect($turn->outcomes)
                    ->reject(fn (CheckOutcome $outcome): bool => $outcome->passed)
                    ->all(),
            );
        }

        if ($run->crash !== null) {
            $sections[] = $this->crashSection($run, $runNumber);
        }

        return $sections;
    }

    protected function crashSection(RunResult $run, int $runNumber): string
    {
        if ($run->crashedTurn === null) {
            return "Run {$runNumber} crashed: {$run->crash}";
        }

        $turnNumber = count($run->turns) + 1;

        return $this->section("Run {$runNumber}, turn {$turnNumber} crashed: {$run->crash}", $run->crashedTurn);
    }

    protected function turnStatus(TurnResult $turn): string
    {
        return $turn->failed() ? 'failed' : "could not be judged: {$turn->judgeError}";
    }

    /** @param array<int, CheckOutcome> $outcomes */
    protected function section(string $heading, Transcript $transcript, array $outcomes = []): string
    {
        $lines = [
            $heading,
            '  Prompt:',
            $this->indent($transcript->prompt, 4),
            ...$this->toolCalls($transcript),
            '  Answer:',
            $this->indent($transcript->answer() !== '' ? $transcript->answer() : '(no answer)', 4),
        ];

        if ($outcomes !== []) {
            $lines[] = '  Failed checks:';

            foreach ($outcomes as $outcome) {
                $lines[] = "    ✗ {$outcome->label}";

                if ($outcome->reason !== null) {
                    $lines[] = $this->indent($outcome->reason, 6);
                }
            }
        }

        return implode("\n", $lines);
    }

    /** @return array<int, string> */
    protected function toolCalls(Transcript $transcript): array
    {
        if ($transcript->toolCalls === []) {
            return ['  Tool calls: none'];
        }

        return [
            '  Tool calls:',
            ...collect($transcript->toolCalls)
                ->map(fn (ToolCallRecord $toolCall): string => "    - {$this->toolCall($toolCall)}")
                ->values(),
        ];
    }

    protected function toolCall(ToolCallRecord $toolCall): string
    {
        $arguments = $toolCall->arguments !== []
            ? " {$toolCall->encodedArguments(ToolCallRecord::ShortArgumentsLength)}"
            : '';

        $status = collect([$toolCall->status->label(), $toolCall->decisionLabel()])
            ->filter()
            ->implode(', ');

        return "{$toolCall->name}{$arguments} ({$status})";
    }

    protected function indent(string $text, int $spaces): string
    {
        return Str::of($text)
            ->replace(["\r\n", "\r"], "\n")
            ->replaceMatches('/^(?=.)/m', Str::repeat(' ', $spaces))
            ->toString();
    }
}
