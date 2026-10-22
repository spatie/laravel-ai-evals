<?php

use Spatie\LaravelAiTests\Judge\JudgeAgent;

return [
    'results_path' => base_path('tests/AiTests/results'),

    'runs' => 3,

    'min_pass_rate' => 0.66,

    'judge' => [
        'agent' => JudgeAgent::class,
        'provider' => env('AI_TESTS_JUDGE_PROVIDER'),
        'model' => env('AI_TESTS_JUDGE_MODEL'),
    ],

    'viewer' => [
        'enabled' => true,
        'prefix' => 'ai-tests',
    ],
];
