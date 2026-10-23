<?php

namespace Spatie\LaravelAiTests\Judge\Runners;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StructuredAgentResponse;
use LogicException;
use Spatie\LaravelAiTests\Judge\JudgeOutput;

class AgentJudgeRunner implements JudgeRunner
{
    public function run(Agent $agent, string $prompt): JudgeOutput
    {
        $response = $agent->prompt(
            $prompt,
            provider: config('ai-tests.judge.provider'),
            model: config('ai-tests.judge.model'),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new LogicException('The judge agent '.$agent::class.' must have structured output.');
        }

        return new JudgeOutput($response['verdicts'] ?? [], $response->meta->model);
    }
}
