<?php

namespace Spatie\LaravelAiTests\Judge\Runners;

use Laravel\Ai\Contracts\Agent;
use Spatie\LaravelAiTests\Judge\JudgeOutput;

interface JudgeRunner
{
    public function run(Agent $agent, string $prompt): JudgeOutput;
}
