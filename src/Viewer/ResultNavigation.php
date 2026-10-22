<?php

namespace Spatie\LaravelAiTests\Viewer;

use Illuminate\Support\Collection;

class ResultNavigation
{
    public function __construct(
        public int $position,
        public int $total,
        public ?ResultFile $previous,
        public ?ResultFile $next,
    ) {}

    /** @param Collection<int, ResultFile> $results */
    public static function at(Collection $results, int $index): self
    {
        return new self(
            position: $index + 1,
            total: $results->count(),
            previous: $results->get($index - 1),
            next: $results->get($index + 1),
        );
    }
}
