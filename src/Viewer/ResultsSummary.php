<?php

namespace Spatie\LaravelAiTests\Viewer;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ResultsSummary
{
    public function __construct(
        public int $failedCount,
        public int $passedCount,
        public ?CarbonImmutable $lastRun,
        public ?string $usualMinPassRate,
    ) {}

    /** @param Collection<int, ResultFile> $results */
    public static function of(Collection $results): self
    {
        return new self(
            failedCount: $results->filter(fn (ResultFile $result): bool => $result->status() === 'failed')->count(),
            passedCount: $results->filter(fn (ResultFile $result): bool => $result->status() === 'passed')->count(),
            lastRun: $results->map(fn (ResultFile $result): ?CarbonImmutable => $result->ranAt())->filter()->max(),
            usualMinPassRate: static::usualMinPassRate($results),
        );
    }

    /** @param Collection<int, ResultFile> $results */
    protected static function usualMinPassRate(Collection $results): ?string
    {
        return $results
            ->map(fn (ResultFile $result): ?string => $result->percentage('min_pass_rate'))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();
    }
}
