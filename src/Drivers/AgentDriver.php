<?php

namespace Spatie\LaravelAiTests\Drivers;

use Closure;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\AgentResponse;
use LogicException;

class AgentDriver implements Driver
{
    protected ?Agent $conversation = null;

    /** @param class-string<Agent>|Closure(): Agent $agent */
    public function __construct(
        protected string|Closure $agent,
        protected ?object $as = null,
    ) {}

    public function startConversation(): void
    {
        $agent = $this->agent instanceof Closure
            ? ($this->agent)()
            : app($this->agent);

        if ($this->as !== null) {
            $agent = $this->forParticipant($agent);
        }

        $this->conversation = $agent;
    }

    public function prompt(string $message): AgentResponse
    {
        return $this->conversation()->prompt($message);
    }

    public function decide(array $decisions): AgentResponse
    {
        return $this->conversation()->prompt(Decisions::from($decisions));
    }

    protected function forParticipant(Agent $agent): Agent
    {
        if (! method_exists($agent, 'forParticipant')) {
            throw new LogicException('The agent '.$agent::class.' does not remember conversations. Use the RemembersConversations trait to run it as a user.');
        }

        $agent->forParticipant($this->as);

        return $agent;
    }

    protected function conversation(): Agent
    {
        if ($this->conversation === null) {
            throw new LogicException('Start a conversation before prompting the agent.');
        }

        return $this->conversation;
    }
}
