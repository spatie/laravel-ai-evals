<?php

namespace Spatie\LaravelAiTests\Viewer;

use Illuminate\Support\Arr;

class TurnSection
{
    /**
     * @param  array<int, CheckRow>  $checks
     * @param  array<int, RunSection>  $runs
     */
    public function __construct(
        public int $number,
        public ?string $prompt,
        public array $checks,
        public array $runs,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromData(int $number, array $data): self
    {
        $checks = array_values(Value::array($data['checks'] ?? null));
        $runs = array_values(Value::array($data['runs'] ?? null));

        return new self(
            number: $number,
            prompt: Value::string($data['prompt'] ?? null),
            checks: array_map(
                fn (mixed $check): CheckRow => CheckRow::fromData(Value::array($check)),
                $checks,
            ),
            runs: collect($runs)
                ->map(fn (mixed $run, int $index): RunSection => RunSection::fromData($index + 1, Value::array($run)))
                ->all(),
        );
    }

    public function checksPassedInEveryRun(): int
    {
        return count(array_filter(
            $this->checks,
            fn (CheckRow $check): bool => $check->status() === Status::Passed,
        ));
    }

    public function checksStatus(): Status
    {
        $statuses = array_map(fn (CheckRow $check): Status => $check->status(), $this->checks);

        return match (true) {
            in_array(Status::Failed, $statuses, true) => Status::Failed,
            in_array(Status::Partial, $statuses, true) => Status::Partial,
            in_array(Status::Unknown, $statuses, true) => Status::Unknown,
            default => Status::Passed,
        };
    }

    /** @return array<int, array{checkHtml: string, notes: array<int, RunNote>}> */
    public function failureGroups(): array
    {
        $groups = [];

        foreach ($this->runs as $run) {
            foreach ($run->verdicts as $verdict) {
                if ($verdict->passed) {
                    continue;
                }

                $groups[$verdict->check] ??= ['checkHtml' => $verdict->checkHtml(), 'notes' => []];
                $groups[$verdict->check]['notes'][] = $verdict;
            }
        }

        $labels = array_column($this->checks, 'label');

        uksort($groups, fn (string $a, string $b): int => $this->position($labels, $a) <=> $this->position($labels, $b));

        return array_values($groups);
    }

    /** @param array<int, string> $labels */
    protected function position(array $labels, string $label): int
    {
        $position = array_search($label, $labels, true);

        return $position === false ? PHP_INT_MAX : $position;
    }

    /** @return array<int, RunSection> */
    public function crashedRuns(): array
    {
        return array_values(array_filter($this->runs, fn (RunSection $run): bool => $run->crash !== null));
    }

    /** @return array<int, RunSection> */
    public function judgeErrorRuns(): array
    {
        return array_values(array_filter($this->runs, fn (RunSection $run): bool => $run->judgeError !== null));
    }

    public function selectedRun(): ?RunSection
    {
        $reachedRuns = array_values(array_filter($this->runs, fn (RunSection $run): bool => ! $run->notReached));

        $failedRun = Arr::first($reachedRuns, fn (RunSection $run): bool => ! $run->passed);

        return $failedRun ?? $reachedRuns[0] ?? $this->runs[0] ?? null;
    }
}
