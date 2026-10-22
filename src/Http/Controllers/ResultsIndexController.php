<?php

namespace Spatie\LaravelAiTests\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Viewer\ResultFile;
use Spatie\LaravelAiTests\Viewer\ResultsSummary;

class ResultsIndexController
{
    public function __invoke(): View
    {
        $resultsPath = config('ai-tests.results_path');

        $results = ResultFile::allIn($resultsPath);

        return view('ai-tests::index', [
            'results' => $results,
            'summary' => ResultsSummary::of($results),
            'resultsPath' => Str::after($resultsPath, base_path().DIRECTORY_SEPARATOR),
        ]);
    }
}
