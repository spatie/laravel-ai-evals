<?php

namespace Spatie\LaravelAiTests\Viewer;

class RunSection
{
    /**
     * @param  array<int, ToolCallRow>  $toolCalls
     * @param  array<int, RunNote>  $verdicts
     */
    public function __construct(
        public int $number,
        public bool $passed,
        public ?string $crash,
        public array $toolCalls,
        public ?string $answer,
        public array $verdicts,
        public bool $notReached = false,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromData(int $fallbackNumber, array $data): self
    {
        $number = is_numeric($data['run'] ?? null) ? (int) $data['run'] : $fallbackNumber;
        $toolCalls = is_array($data['tool_calls'] ?? null) ? $data['tool_calls'] : [];
        $verdicts = is_array($data['verdicts'] ?? null) ? $data['verdicts'] : [];

        return new self(
            number: $number,
            passed: ($data['passed'] ?? false) === true,
            crash: isset($data['crash']) ? (string) $data['crash'] : null,
            toolCalls: array_values(array_map(
                fn (mixed $toolCall): ToolCallRow => ToolCallRow::fromData(is_array($toolCall) ? $toolCall : []),
                $toolCalls,
            )),
            answer: isset($data['answer']) ? (string) $data['answer'] : null,
            verdicts: array_values(array_map(
                fn (array $verdict): RunNote => RunNote::fromData($number, $verdict),
                array_filter($verdicts, fn (mixed $verdict): bool => is_array($verdict)),
            )),
            notReached: ($data['not_reached'] ?? false) === true,
        );
    }

    public function status(): string
    {
        return $this->passed ? 'passed' : 'failed';
    }

    public function answerHtml(): ?string
    {
        if ($this->answer === null || trim($this->answer) === '') {
            return null;
        }

        return Html::markdown($this->answer);
    }
}
