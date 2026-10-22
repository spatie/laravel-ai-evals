<?php

namespace Spatie\LaravelAiTests\Judge;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;
use Spatie\LaravelAiTests\Checks\RubricCriterion;
use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\ToolCallRecord;
use Spatie\LaravelAiTests\Transcript\Transcript;

class Judge
{
    /**
     * @param  array<int, RubricCriterion>  $rubric
     * @param  array<int, Transcript>  $earlierTurns
     */
    public function evaluate(array $rubric, Transcript $transcript, array $earlierTurns = []): JudgeResult
    {
        if ($rubric === []) {
            return new JudgeResult([]);
        }

        $agent = $this->agent();

        $response = $agent->prompt(
            $this->prompt($rubric, $transcript, $earlierTurns),
            provider: config('ai-tests.judge.provider'),
            model: config('ai-tests.judge.model'),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new LogicException('The judge agent '.$agent::class.' must have structured output.');
        }

        $verdicts = collect($response['verdicts'] ?? [])->keyBy('criterion');

        $outcomes = array_map(
            fn (RubricCriterion $criterion, int $index): CheckOutcome => $this->outcome($criterion, $verdicts->get($index + 1)),
            array_values($rubric),
            array_keys(array_values($rubric)),
        );

        return new JudgeResult($outcomes, $response->meta->model);
    }

    protected function agent(): Agent
    {
        return app(config('ai-tests.judge.agent'));
    }

    /**
     * @param  array<int, RubricCriterion>  $rubric
     * @param  array<int, Transcript>  $earlierTurns
     */
    protected function prompt(array $rubric, Transcript $transcript, array $earlierTurns): string
    {
        $sections = [];

        if ($earlierTurns !== []) {
            $sections[] = "# Earlier turns\n\nContext only. Do not grade these turns.";

            foreach (array_values($earlierTurns) as $index => $earlierTurn) {
                $sections[] = '## Turn '.($index + 1)."\n\n{$this->earlierTurn($earlierTurn)}";
            }
        }

        $sections[] = "# Turn to grade\n\n{$this->transcript($transcript)}";
        $sections[] = "# Criteria\n\n{$this->numberedCriteria($rubric)}";

        return implode("\n\n", $sections);
    }

    protected function earlierTurn(Transcript $transcript): string
    {
        $lines = ["User: {$transcript->prompt}", ''];

        foreach ($transcript->toolCalls as $toolCall) {
            $lines[] = $this->toolCallSummary($toolCall);
        }

        if ($transcript->toolCalls !== []) {
            $lines[] = '';
        }

        $lines[] = 'Assistant answer:';
        $lines[] = $transcript->answer() !== '' ? $transcript->answer() : '(no text)';

        return implode("\n", $lines);
    }

    protected function transcript(Transcript $transcript): string
    {
        $lines = ["User: {$transcript->prompt}", ''];

        foreach ($transcript->toolCalls as $toolCall) {
            $lines[] = $this->toolCall($toolCall);
            $lines[] = '';
        }

        $lines[] = 'Assistant answer:';
        $lines[] = $transcript->answer() !== '' ? $transcript->answer() : '(no text)';

        return implode("\n", $lines);
    }

    protected function toolCall(ToolCallRecord $toolCall): string
    {
        return implode("\n", [
            $this->toolCallSummary($toolCall),
            'Result: '.($toolCall->result ?? '(none)'),
        ]);
    }

    protected function toolCallSummary(ToolCallRecord $toolCall): string
    {
        $arguments = json_encode($toolCall->arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $decision = $toolCall->decisionLabel() !== null ? ", user {$toolCall->decisionLabel()} it" : '';

        return "Tool call `{$toolCall->name}` {$arguments} ({$toolCall->status->value}{$decision})";
    }

    /** @param array<int, RubricCriterion> $rubric */
    protected function numberedCriteria(array $rubric): string
    {
        return collect(array_values($rubric))
            ->map(fn (RubricCriterion $criterion, int $index): string => ($index + 1).". {$criterion->criterion}")
            ->implode("\n");
    }

    /** @param array{criterion: int, reason: string, pass: bool}|null $verdict */
    protected function outcome(RubricCriterion $criterion, ?array $verdict): CheckOutcome
    {
        if ($verdict === null) {
            return new CheckOutcome($criterion, passed: false, reason: 'The judge returned no verdict.');
        }

        return new CheckOutcome($criterion, passed: $verdict['pass'], reason: $verdict['reason']);
    }
}
