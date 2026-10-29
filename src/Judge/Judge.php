<?php

namespace Spatie\LaravelAiEvals\Judge;

use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;
use Spatie\LaravelAiEvals\AiEvals;
use Spatie\LaravelAiEvals\Results\CheckOutcome;
use Spatie\LaravelAiEvals\Transcript\ToolCallRecord;
use Spatie\LaravelAiEvals\Transcript\Transcript;

class Judge
{
    /**
     * @param  list<string>  $criteria
     * @param  list<Transcript>  $earlierTurns
     * @return array<int, CheckOutcome>
     */
    public function evaluate(array $criteria, Transcript $transcript, array $earlierTurns): array
    {
        if ($criteria === []) {
            return [];
        }

        $verdicts = $this->verdicts($this->prompt($criteria, $transcript, $earlierTurns), count($criteria));

        return collect($criteria)
            ->map(fn (string $criterion, int $index): CheckOutcome => new CheckOutcome(
                $criterion,
                passed: $verdicts[$index + 1]['pass'],
                reason: $verdicts[$index + 1]['reason'],
            ))
            ->all();
    }

    /** @return Collection<int, array{criterion: int, reason: string, pass: bool}> */
    protected function verdicts(string $prompt, int $criterionCount): Collection
    {
        $agent = AiEvals::judgeAgent();

        $response = $agent->prompt(
            $prompt,
            provider: AiEvals::judgeProvider(),
            model: AiEvals::judgeModel(),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new LogicException('The judge agent '.$agent::class.' must have structured output.');
        }

        $verdicts = $response['verdicts'] ?? [];

        $this->ensureValidVerdicts($verdicts);
        $this->ensureOneVerdictPerCriterion($verdicts, $criterionCount);

        return collect($verdicts)->keyBy('criterion');
    }

    /**
     * @param  list<string>  $criteria
     * @param  list<Transcript>  $earlierTurns
     */
    protected function prompt(array $criteria, Transcript $transcript, array $earlierTurns): string
    {
        $sections = [];

        if ($earlierTurns !== []) {
            $sections[] = "# Earlier turns\n\nContext only. Do not grade these turns.";

            foreach ($earlierTurns as $index => $earlierTurn) {
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
        $toolCalls = collect($transcript->toolCalls)
            ->map(fn (ToolCallRecord $toolCall): string => $this->toolCallSummary($toolCall))
            ->implode("\n");

        return collect(["User: {$transcript->prompt}", $toolCalls, $this->answer($transcript)])
            ->filter()
            ->implode("\n\n");
    }

    protected function transcript(Transcript $transcript): string
    {
        return collect($transcript->toolCalls)
            ->map(fn (ToolCallRecord $toolCall): string => $this->toolCall($toolCall))
            ->prepend("User: {$transcript->prompt}")
            ->push($this->answer($transcript))
            ->implode("\n\n");
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

    /** @param list<string> $criteria */
    protected function numberedCriteria(array $criteria): string
    {
        return collect($criteria)
            ->map(fn (string $criterion, int $index): string => ($index + 1).". {$criterion}")
            ->implode("\n");
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
        $numbers = collect($verdicts)
            ->pluck('criterion')
            ->sort()
            ->values()
            ->all();

        if ($numbers !== range(1, $criterionCount)) {
            $returned = implode(', ', $numbers);

            throw new LogicException("The judge must return one verdict for each criterion from 1 to {$criterionCount}, but returned verdicts for criteria [{$returned}].");
        }
    }
}
