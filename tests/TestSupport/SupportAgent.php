<?php

namespace Spatie\LaravelAiEvals\Tests\TestSupport;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class SupportAgent implements Agent, Conversational, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function instructions(): string
    {
        return 'You help customers of a web shop.';
    }

    public function tools(): iterable
    {
        return [new LookupOrderTool];
    }
}
