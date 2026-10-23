<?php

use Spatie\LaravelAiTests\Judge\JudgeAgent;

return [
    'results_path' => base_path('tests/AiTests/results'),

    'runs' => 3,

    'forced_runs' => env('AI_TESTS_RUNS'),

    'min_pass_rate' => 0.66,

    'judge' => [
        'agent' => JudgeAgent::class,
        'runner' => env('AI_TESTS_JUDGE_RUNNER', 'agent'),
        'provider' => env('AI_TESTS_JUDGE_PROVIDER'),
        'model' => env('AI_TESTS_JUDGE_MODEL'),
        'claude' => [
            'binary' => env('AI_TESTS_JUDGE_CLAUDE_BINARY', 'claude'),
            'model' => env('AI_TESTS_JUDGE_CLAUDE_MODEL'),
            'timeout' => 300,
        ],
        'codex' => [
            'binary' => env('AI_TESTS_JUDGE_CODEX_BINARY', 'codex'),
            'model' => env('AI_TESTS_JUDGE_CODEX_MODEL'),
            'timeout' => 300,
        ],
    ],

    'viewer' => [
        'enabled' => true,
        'prefix' => 'ai-tests',
    ],
];
