<?php

namespace Spatie\LaravelAiTests\Judge;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;
use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\ToolCallRecord;
use Spatie\LaravelAiTests\Transcript\Transcript;

class Judge
{
    /** @param array<int, string> $rubric */
    public function evaluate(array $rubric, Transcript $transcript): JudgeResult
    {
        if ($rubric === []) {
            return new JudgeResult([]);
        }

        $agent = $this->agent();

        $response = $agent->prompt(
            "# Transcript\n\n{$this->transcript($transcript)}\n\n# Criteria\n\n{$this->numberedCriteria($rubric)}",
            provider: config('ai-tests.judge.provider'),
            model: config('ai-tests.judge.model'),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new LogicException('The judge agent '.$agent::class.' must have structured output.');
        }

        $verdicts = collect($response['verdicts'] ?? [])->keyBy('criterion');

        $outcomes = array_map(
            fn (string $criterion, int $index): CheckOutcome => $this->outcome($criterion, $verdicts->get($index + 1)),
            $rubric,
            array_keys($rubric),
        );

        return new JudgeResult($outcomes, $response->meta->model);
    }

    protected function agent(): Agent
    {
        return app(config('ai-tests.judge.agent'));
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
        $arguments = json_encode($toolCall->arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $decision = $toolCall->decisionLabel() !== null ? ", user {$toolCall->decisionLabel()} it" : '';

        return implode("\n", [
            "Tool call `{$toolCall->name}` {$arguments} ({$toolCall->status->value}{$decision})",
            'Result: '.($toolCall->result ?? '(none)'),
        ]);
    }

    /** @param array<int, string> $rubric */
    protected function numberedCriteria(array $rubric): string
    {
        return collect($rubric)
            ->map(fn (string $criterion, int $index): string => ($index + 1).". {$criterion}")
            ->implode("\n");
    }

    /** @param array{criterion: int, reason: string, pass: bool}|null $verdict */
    protected function outcome(string $criterion, ?array $verdict): CheckOutcome
    {
        if ($verdict === null) {
            return new CheckOutcome($criterion, passed: false, reason: 'The judge returned no verdict.', judged: true);
        }

        return new CheckOutcome($criterion, passed: $verdict['pass'], reason: $verdict['reason'], judged: true);
    }
}
