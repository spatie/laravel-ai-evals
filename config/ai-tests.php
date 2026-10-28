<?php

use Spatie\LaravelAiTests\Judge\JudgeAgent;

return [
    /*
     * Every AI test writes its result file to this directory. Commit these
     * files, so a diff shows how a change moved the results.
     */
    'results_path' => base_path('tests/AiTests/results'),

    /*
     * The number of fresh conversations per test. A test can set its own
     * number with `runs()`.
     */
    'runs' => 3,

    /*
     * When set, this number of runs is used for every test, including tests
     * that call `runs()`. Use `AI_TESTS_RUNS=1` to run a test once while you
     * work on it.
     */
    'forced_runs' => env('AI_TESTS_RUNS'),

    /*
     * The minimum share of runs that must pass for a test to pass. A test can
     * set its own minimum with `minPassRate()`.
     */
    'min_pass_rate' => 0.66,

    'judge' => [
        /*
         * The agent that grades the rubric criteria. Extend `JudgeAgent` to
         * give the judge context about your product.
         */
        'agent' => JudgeAgent::class,

        /*
         * The provider and model of the judge. When they are not set, the
         * judge uses your default Laravel AI provider and model.
         */
        'provider' => env('AI_TESTS_JUDGE_PROVIDER'),
        'model' => env('AI_TESTS_JUDGE_MODEL'),
    ],

    'viewer' => [
        /*
         * The results viewer is only available in the local environment.
         */
        'enabled' => true,

        /*
         * The URL prefix of the results viewer.
         */
        'prefix' => 'ai-tests',
    ],
];
