<?php

namespace Spatie\LaravelAiTests\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Spatie\LaravelAiTests\Results\ResultFile;

class ResultsIndexController
{
    public function __invoke(): View
    {
        $results = collect(File::glob(config('ai-tests.results_path').'/*.md'))
            ->map(fn (string $path): ResultFile => ResultFile::fromPath($path))
            ->sortBy(fn (ResultFile $result): string => $result->title())
            ->values();

        return view('ai-tests::index', ['results' => $results]);
    }
}
