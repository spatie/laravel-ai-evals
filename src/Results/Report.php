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
        $sections = array_merge(
            [$this->summary()],
            ...array_map(
                fn (RunResult $run, int $index): array => $this->failedTurns($run, $index + 1),
                $this->result->runs,
                array_keys($this->result->runs),
            ),
        );

        return implode("\n\n", $sections);
    }

    protected function summary(): string
    {
        $result = $this->result;

        if ($result->judgedRuns() === 0) {
            return 'No run could be judged, because the judge failed in every run.';
        }

        $summary = "{$result->passedRuns()}/{$result->judgedRuns()} runs passed, below the minimum pass rate of {$result->minPassRate}.";

        if ($result->unjudgedRuns() > 0) {
            $summary .= " {$result->unjudgedRuns()} unjudged ".Str::plural('run', $result->unjudgedRuns()).' left out.';
        }

        return $summary;
    }

    /** @return array<int, string> */
    protected function failedTurns(RunResult $run, int $runNumber): array
    {
        $sections = [];

        foreach ($run->turns as $index => $turn) {
            if (! $turn->passed()) {
                $turnNumber = $index + 1;

                $sections[] = $this->section(
                    "Run {$runNumber}, turn {$turnNumber} {$this->turnStatus($turn)}",
                    $turn->transcript,
                    array_filter($turn->outcomes, fn (CheckOutcome $outcome): bool => ! $outcome->passed),
                );
            }
        }

        if ($run->crash !== null) {
            $crashedTurnNumber = count($run->turns) + 1;

            $heading = $run->crashedTurn !== null
                ? "Run {$runNumber}, turn {$crashedTurnNumber} crashed: {$run->crash}"
                : "Run {$runNumber} crashed: {$run->crash}";

            $sections[] = $this->section($heading, $run->crashedTurn);
        }

        return $sections;
    }

    protected function turnStatus(TurnResult $turn): string
    {
        return match (true) {
            $turn->failed() && $turn->judgeError !== null => "failed, and the judge failed: {$turn->judgeError}",
            $turn->failed() => 'failed',
            default => "could not be judged: {$turn->judgeError}",
        };
    }

    /** @param array<int, CheckOutcome> $outcomes */
    protected function section(string $heading, ?Transcript $transcript, array $outcomes = []): string
    {
        if ($transcript === null) {
            return $heading;
        }

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
            ...array_values(array_map(
                fn (ToolCallRecord $toolCall): string => "    - {$this->toolCall($toolCall)}",
                $transcript->toolCalls,
            )),
        ];
    }

    protected function toolCall(ToolCallRecord $toolCall): string
    {
        $arguments = $toolCall->arguments !== []
            ? " {$toolCall->encodedArguments(ToolCallRecord::ShortArgumentsLength)}"
            : '';

        $status = implode(', ', array_filter([
            $toolCall->status->label(),
            $toolCall->decisionLabel(),
        ]));

        return "{$toolCall->name}{$arguments} ({$status})";
    }

    protected function indent(string $text, int $spaces): string
    {
        return Str::of($text)
            ->replace(["\r\n", "\r"], "\n")
            ->replaceMatches('/^(?=.)/m', str_repeat(' ', $spaces))
            ->toString();
    }
}
