<?php

use Spatie\LaravelAiTests\AiTest;
use Spatie\LaravelAiTests\Results\ResultFile;
use Spatie\LaravelAiTests\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function makeAiTest(string $description): AiTest
{
    return new AiTest($description, __FILE__);
}

function resultFile(string $slug): ResultFile
{
    return ResultFile::fromPath(config('ai-tests.results_path')."/{$slug}.yaml");
}
