<?php

namespace Spatie\LaravelAiTests\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Results\ResultFile;

class ShowResultController
{
    public function __invoke(string $slug): View
    {
        $path = config('ai-tests.results_path')."/{$slug}.md";

        abort_unless(File::isFile($path), 404);

        $result = ResultFile::fromPath($path);

        $html = Str::markdown($result->body, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return view('ai-tests::show', [
            'result' => $result,
            'html' => $html,
        ]);
    }
}
