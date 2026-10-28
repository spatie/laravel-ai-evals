<?php

namespace Spatie\LaravelAiEvals\Judge;

use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;
use Spatie\LaravelAiEvals\AiEvals;
use Spatie\LaravelAiEvals\Checks\JudgeCriterion;
use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\ToolCallRecord;
use Spatie\LaravelAiEvals\Transcript\Transcript;

class Judge
{
    /**
     * @param  list<JudgeCriterion>  $criteria
     * @param  array<int, Transcript>  $earlierTurns
     * @return array<int, CheckOutcome>
     */
    public function evaluate(array $criteria, Transcript $transcript, array $earlierTurns = []): array
    {
        if ($criteria === []) {
            return [];
        }

        $agent = AiEvals::judgeAgent();

        $response = $agent->prompt(
            $this->prompt($criteria, $transcript, $earlierTurns),
            provider: AiEvals::judgeProvider(),
            model: AiEvals::judgeModel(),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new LogicException('The judge agent '.$agent::class.' must have structured output.');
        }

        $verdicts = $response['verdicts'] ?? [];

        $this->ensureValidVerdicts($verdicts);
        $this->ensureOneVerdictPerCriterion($verdicts, count($criteria));

        $verdicts = array_column($verdicts, null, 'criterion');

        return array_map(
            fn (JudgeCriterion $criterion, int $index): CheckOutcome => new CheckOutcome(
                $criterion->label(),
                passed: $verdicts[$index + 1]['pass'],
                reason: $verdicts[$index + 1]['reason'],
            ),
            $criteria,
            array_keys($criteria),
        );
    }

    /**
     * @param  list<JudgeCriterion>  $criteria
     * @param  array<int, Transcript>  $earlierTurns
     */
    protected function prompt(array $criteria, Transcript $transcript, array $earlierTurns): string
    {
        $sections = [];

        if ($earlierTurns !== []) {
            $sections[] = "# Earlier turns\n\nContext only. Do not grade these turns.";

            foreach (array_values($earlierTurns) as $index => $earlierTurn) {
                $turnNumber = $index + 1;

                $sections[] = "## Turn {$turnNumber}\n\n{$this->earlierTurn($earlierTurn)}";
            }
        }

        $sections[] = "# Turn to grade\n\n{$this->transcript($transcript)}";
        $sections[] = "# Criteria\n\n{$this->numberedCriteria($criteria)}";

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

        $lines[] = $this->answer($transcript);

        return implode("\n", $lines);
    }

    protected function transcript(Transcript $transcript): string
    {
        $lines = ["User: {$transcript->prompt}", ''];

        foreach ($transcript->toolCalls as $toolCall) {
            $lines[] = $this->toolCall($toolCall);
            $lines[] = '';
        }

        $lines[] = $this->answer($transcript);

        return implode("\n", $lines);
    }

    protected function answer(Transcript $transcript): string
    {
        $answer = $transcript->answer() !== '' ? $transcript->answer() : '(no text)';

        return "Assistant answer:\n{$answer}";
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
        $decision = $toolCall->decisionLabel() !== null ? ", user {$toolCall->decisionLabel()} it" : '';

        return "Tool call `{$toolCall->name}` {$toolCall->encodedArguments()} ({$toolCall->status->label()}{$decision})";
    }

    /** @param list<JudgeCriterion> $criteria */
    protected function numberedCriteria(array $criteria): string
    {
        return implode("\n", array_map(
            fn (JudgeCriterion $criterion, int $index): string => ($index + 1).". {$criterion->criterion}",
            $criteria,
            array_keys($criteria),
        ));
    }

    protected function ensureValidVerdicts(mixed $verdicts): void
    {
        if (! is_array($verdicts) || ! array_is_list($verdicts)) {
            throw new LogicException("The judge must return a list of verdicts, but got {$this->encode($verdicts)}.");
        }

        foreach ($verdicts as $index => $verdict) {
            if (! $this->isValidVerdict($verdict)) {
                $position = $index + 1;

                throw new LogicException("The judge returned a malformed verdict at position {$position}. Each verdict needs an integer `criterion`, a boolean `pass` and a string `reason`, but got {$this->encode($verdict)}.");
            }
        }
    }

    protected function isValidVerdict(mixed $verdict): bool
    {
        return is_array($verdict)
            && is_int($verdict['criterion'] ?? null)
            && is_bool($verdict['pass'] ?? null)
            && is_string($verdict['reason'] ?? null);
    }

    protected function encode(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<int, array{criterion: int, reason: string, pass: bool}> $verdicts */
    protected function ensureOneVerdictPerCriterion(array $verdicts, int $criterionCount): void
    {
        $numbers = array_column($verdicts, 'criterion');

        sort($numbers);

        if ($numbers !== range(1, $criterionCount)) {
            $returned = implode(', ', $numbers);

            throw new LogicException("The judge must return one verdict for each criterion from 1 to {$criterionCount}, but returned verdicts for criteria [{$returned}].");
        }
    }
}
