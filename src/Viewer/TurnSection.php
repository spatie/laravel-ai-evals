<?php

namespace Spatie\LaravelAiTests\Viewer;

use Illuminate\Support\Str;

class TurnSection
{
    /**
     * @param  array<int, CheckRow>  $checks
     * @param  array<int, RunNote>  $judgeNotes
     * @param  array<int, RunNote>  $crashes
     * @param  array<int, array{name: string, arguments: ?string}>|null  $toolCalls
     */
    public function __construct(
        public int $number,
        public ?string $promptHtml,
        public array $checks,
        public array $judgeNotes,
        public array $crashes,
        public ?string $exampleLabel,
        public ?array $toolCalls,
        public ?string $answerHtml,
        public ?string $extraHtml,
    ) {}

    public function failingChecks(): int
    {
        return count(array_filter(
            $this->checks,
            fn (CheckRow $check): bool => in_array($check->status(), ['failed', 'partial'], true),
        ));
    }

    public function hasExample(): bool
    {
        return $this->exampleLabel !== null;
    }

    public function exampleStatus(): ?string
    {
        if ($this->exampleLabel === null) {
            return null;
        }

        return match (true) {
            str_contains($this->exampleLabel, 'failed') => 'failed',
            str_contains($this->exampleLabel, 'passed') => 'passed',
            default => null,
        };
    }

    public function exampleRun(): ?int
    {
        if (preg_match('/run (\d+)/', $this->exampleLabel ?? '', $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /** @return array<int, array{checkHtml: ?string, notes: array<int, RunNote>}> */
    public function judgeNoteGroups(): array
    {
        $groups = [];

        foreach ($this->judgeNotes as $note) {
            $key = $note->check ?? '';

            $groups[$key] ??= ['checkHtml' => $this->checkHtml($note->check), 'notes' => []];
            $groups[$key]['notes'][] = $note;
        }

        return array_values($groups);
    }

    protected function checkHtml(?string $truncatedLabel): ?string
    {
        if ($truncatedLabel === null) {
            return null;
        }

        $prefix = Str::endsWith($truncatedLabel, '...') ? Str::beforeLast($truncatedLabel, '...') : $truncatedLabel;

        foreach ($this->checks as $check) {
            if (str_starts_with($check->label, $prefix)) {
                return $check->labelHtml;
            }
        }

        return ResultBody::inlineMarkdown($truncatedLabel);
    }
}
