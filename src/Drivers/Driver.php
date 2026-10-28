<?php

namespace Spatie\LaravelAiEvals\Drivers;

use Laravel\Ai\Responses\AgentResponse;

interface Driver
{
    public function startConversation(): void;

    public function prompt(string $message): AgentResponse;

    /** @param array<string, bool> $decisions */
    public function decide(array $decisions): AgentResponse;
}
