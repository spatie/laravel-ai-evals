<?php

namespace Spatie\LaravelAiTests\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Spatie\LaravelAiTests\Results\ResultFile;
use Spatie\LaravelAiTests\Viewer\ResultBody;

class ShowResultController
{
    public function __invoke(string $slug): View
    {
        $path = config('ai-tests.results_path')."/{$slug}.md";

        abort_unless(File::isFile($path), 404);

        $result = ResultFile::fromPath($path);
        $turns = ResultBody::turns($result->body);

        return view('ai-tests::show', [
            'result' => $result,
            'turns' => $turns,
            'html' => $turns === null ? ResultBody::markdown($result->body) : null,
        ]);
    }
}
