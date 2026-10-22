<?php

namespace Spatie\LaravelAiTests\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Spatie\LaravelAiTests\Results\ResultFile;

class ShowResultController
{
    public function __invoke(string $slug): View
    {
        $path = config('ai-tests.results_path')."/{$slug}.yaml";

        abort_unless(File::isFile($path), 404);

        $result = ResultFile::fromPath($path);

        return view('ai-tests::show', [
            'result' => $result,
            'turns' => $result->turns(),
        ]);
    }
}
