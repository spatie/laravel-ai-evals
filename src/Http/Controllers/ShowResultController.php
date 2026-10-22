<?php

namespace Spatie\LaravelAiTests\Http\Controllers;

use Illuminate\Contracts\View\View;
use Spatie\LaravelAiTests\Viewer\ResultFile;
use Spatie\LaravelAiTests\Viewer\ResultNavigation;

class ShowResultController
{
    public function __invoke(string $slug): View
    {
        $results = ResultFile::allIn(config('ai-tests.results_path'));

        $index = $results->search(fn (ResultFile $result): bool => $result->slug === $slug);

        abort_if($index === false, 404);

        $result = $results[$index];

        return view('ai-tests::show', [
            'result' => $result,
            'turns' => $result->turns(),
            'navigation' => ResultNavigation::at($results, $index),
        ]);
    }
}
