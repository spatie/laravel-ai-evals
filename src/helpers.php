<?php

use Spatie\LaravelAiTests\AiTest;
use Spatie\LaravelAiTests\Results\ResultSlugs;

if (! function_exists('aiTest')) {
    function aiTest(string $description): AiTest
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        $aiTest = new AiTest($description, $caller['file']);

        ResultSlugs::claim($aiTest->slug(), "{$caller['file']}:{$caller['line']}");

        test($description, fn () => $aiTest->execute());

        return $aiTest;
    }
}
