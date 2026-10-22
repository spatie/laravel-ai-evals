<?php

namespace Spatie\LaravelAiTests\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Results\ResultFile;

class ResultsIndexController
{
    public function __invoke(): View
    {
        $resultsPath = config('ai-tests.results_path');

        return view('ai-tests::index', [
            'results' => ResultFile::allIn($resultsPath),
            'resultsPath' => Str::after($resultsPath, base_path().DIRECTORY_SEPARATOR),
        ]);
    }
}
