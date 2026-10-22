<?php

use Spatie\LaravelAiTests\AiTest;

if (! function_exists('aiTest')) {
    function aiTest(string $description): AiTest
    {
        $aiTest = new AiTest($description, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file']);

        test($description, fn () => $aiTest->execute());

        return $aiTest;
    }
}
