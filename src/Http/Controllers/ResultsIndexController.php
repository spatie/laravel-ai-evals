<?php

namespace Spatie\LaravelAiTests\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Results\ResultFile;

class ResultsIndexController
{
    public function __invoke(): View
    {
        $resultsPath = config('ai-tests.results_path');

        $results = collect(File::glob($resultsPath.'/*.md'))
            ->map(fn (string $path): ResultFile => ResultFile::fromPath($path))
            ->sortBy([
                fn (ResultFile $a, ResultFile $b): int => ($a->status() !== 'failed') <=> ($b->status() !== 'failed'),
                fn (ResultFile $a, ResultFile $b): int => strcasecmp($a->title(), $b->title()),
            ])
            ->values();

        return view('ai-tests::index', [
            'results' => $results,
            'resultsPath' => Str::after($resultsPath, base_path().DIRECTORY_SEPARATOR),
        ]);
    }
}
