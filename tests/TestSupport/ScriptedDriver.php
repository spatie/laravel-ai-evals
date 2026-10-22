<?php

namespace Spatie\LaravelAiTests\Tests\TestSupport;

use Laravel\Ai\Responses\AgentResponse;
use Spatie\LaravelAiTests\Drivers\Driver;

class ScriptedDriver implements Driver
{
    /** @param array<int, AgentResponse> $responses */
    public function __construct(
        protected array $responses,
    ) {}

    public function startConversation(): void {}

    public function prompt(string $message): AgentResponse
    {
        return array_shift($this->responses);
    }

    public function decide(array $decisions): AgentResponse
    {
        return array_shift($this->responses);
    }
}
