<?php

namespace Spatie\LaravelAiTests\Viewer;

use Illuminate\Support\Str;

class ResultBody
{
    /** @return array<int, TurnSection>|null */
    public static function turns(string $body): ?array
    {
        $parts = preg_split('/^## Turn (\d+)[ \t]*$/m', $body, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || count($parts) < 3) {
            return null;
        }

        $parser = new self;
        $turns = [];

        for ($index = 1; $index < count($parts); $index += 2) {
            $turns[] = $parser->turn((int) $parts[$index], $parts[$index + 1] ?? '');
        }

        return $turns;
    }

    public static function markdown(string $markdown): string
    {
        return Str::markdown($markdown, self::markdownOptions());
    }

    public static function inlineMarkdown(string $markdown): string
    {
        return trim(Str::inlineMarkdown($markdown, self::markdownOptions()));
    }

    /** @return array<string, mixed> */
    protected static function markdownOptions(): array
    {
        return [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ];
    }

    protected function turn(int $number, string $section): TurnSection
    {
        $exampleParts = preg_split('/^\*\*Example answer\*\*(.*)$/m', $section, 2, PREG_SPLIT_DELIM_CAPTURE);
        $details = $exampleParts[0] ?? $section;

        $prompt = null;
        $tableLines = [];
        $judgeNotes = [];
        $crashes = [];
        $extraLines = [];
        $list = null;

        foreach (preg_split('/\R/', $details) ?: [] as $line) {
            match (true) {
                trim($line) === '' => null,
                str_starts_with($line, '**User:**') => $prompt = trim(Str::after($line, '**User:**')),
                str_starts_with($line, '|') => $tableLines[] = $line,
                trim($line) === '**Judge notes**' => $list = 'judge',
                trim($line) === '**Crashes**' => $list = 'crashes',
                $list === 'judge' && str_starts_with($line, '- ') => $judgeNotes[] = $this->judgeNote(substr($line, 2)),
                $list === 'crashes' && str_starts_with($line, '- ') => $crashes[] = $this->crash(substr($line, 2)),
                default => $extraLines[] = $line,
            };
        }

        [$toolCalls, $answer] = $this->exampleAnswer($exampleParts[2] ?? null);

        return new TurnSection(
            number: $number,
            promptHtml: $prompt !== null ? self::inlineMarkdown($prompt) : null,
            checks: $this->checks($tableLines),
            judgeNotes: $judgeNotes,
            crashes: $crashes,
            exampleLabel: isset($exampleParts[1]) ? trim($exampleParts[1], " \t()") : null,
            toolCalls: $toolCalls,
            answerHtml: $answer !== null ? self::markdown($answer) : null,
            extraHtml: $extraLines !== [] ? self::markdown(implode("\n", $extraLines)) : null,
        );
    }

    /**
     * @param  array<int, string>  $lines
     * @return array<int, CheckRow>
     */
    protected function checks(array $lines): array
    {
        return array_map(function (string $line): CheckRow {
            $cells = array_map(
                fn (string $cell): string => str_replace('\|', '|', trim($cell)),
                preg_split('/(?<!\\\\)\|/', trim($line, " \t|")) ?: [],
            );

            $count = array_pop($cells) ?? '';
            $label = implode(' | ', $cells);

            if (preg_match('/^(\d+)\/(\d+)$/', $count, $matches) !== 1) {
                $label = $label !== '' ? $label : $count;

                return new CheckRow($label, self::inlineMarkdown($label), null, null);
            }

            return new CheckRow($label, self::inlineMarkdown($label), (int) $matches[1], (int) $matches[2]);
        }, array_slice($lines, 2));
    }

    protected function judgeNote(string $line): RunNote
    {
        if (preg_match('/^Run (\d+), "(.*?)": failed\. (.*)$/s', $line, $matches) !== 1) {
            return new RunNote(null, null, self::inlineMarkdown($line));
        }

        return new RunNote((int) $matches[1], $matches[2], self::inlineMarkdown($matches[3]));
    }

    protected function crash(string $line): RunNote
    {
        if (preg_match('/^Run (\d+): (.*)$/s', $line, $matches) !== 1) {
            return new RunNote(null, null, e($line));
        }

        return new RunNote((int) $matches[1], null, e($matches[2]));
    }

    /** @return array{0: array<int, array{name: string, arguments: ?string}>|null, 1: ?string} */
    protected function exampleAnswer(?string $example): array
    {
        if ($example === null) {
            return [null, null];
        }

        $example = trim($example);

        if (! str_starts_with($example, 'Tools:')) {
            return [null, $example !== '' ? $example : null];
        }

        $toolLine = trim(Str::before(Str::after($example, 'Tools:'), "\n"));
        $answer = trim(Str::contains($example, "\n") ? Str::after($example, "\n") : '');

        return [$this->toolCalls($toolLine), $answer !== '' ? $answer : null];
    }

    /** @return array<int, array{name: string, arguments: ?string}> */
    protected function toolCalls(string $line): array
    {
        if ($line === 'none') {
            return [];
        }

        preg_match_all('/`([^`]+)`(?: \((.*?)\))?(?=, `|$)/', $line, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return [['name' => $line, 'arguments' => null]];
        }

        return array_map(fn (array $match): array => [
            'name' => $match[1],
            'arguments' => ($match[2] ?? '') !== '' ? $match[2] : null,
        ], $matches);
    }
}
