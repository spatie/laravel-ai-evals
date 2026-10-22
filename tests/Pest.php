<?php

use Illuminate\Support\Facades\File;
use Spatie\LaravelAiTests\AiTest;
use Spatie\LaravelAiTests\Results\ResultFile;
use Spatie\LaravelAiTests\Tests\TestCase;
use Symfony\Component\Yaml\Yaml;

uses(TestCase::class)->in(__DIR__);

function makeAiTest(string $description): AiTest
{
    return new AiTest($description, __FILE__);
}

function resultFile(string $slug): ResultFile
{
    return ResultFile::fromPath(config('ai-tests.results_path')."/{$slug}.yaml");
}

/** @param array<string, mixed> $data */
function writeResultFile(string $slug, array $data): void
{
    File::put(config('ai-tests.results_path')."/{$slug}.yaml", Yaml::dump($data));
}
