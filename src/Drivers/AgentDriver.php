<?php

namespace Spatie\LaravelAiEvals\Drivers;

use Closure;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\RemembersConversations;
use Laravel\Ai\Middleware\RememberConversation;
use Laravel\Ai\Responses\AgentResponse;
use LogicException;

class AgentDriver implements Driver
{
    protected ?Agent $conversation = null;

    protected bool $prompted = false;

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
        $this->prompted = false;
    }

    public function prompt(string $message): AgentResponse
    {
        $conversation = $this->conversation();

        if ($this->prompted && ! $this->remembers($conversation)) {
            throw new LogicException('The agent '.$conversation::class.' does not remember the conversation, so the next turn would start without the earlier ones. Use the RemembersConversations trait and pass `as:` to the AgentDriver.');
        }

        $this->prompted = true;

        return $conversation->prompt($message);
    }

    public function decide(array $decisions): AgentResponse
    {
        return $this->conversation()->prompt(Decisions::from($decisions));
    }

    protected function forParticipant(Agent $agent): Agent
    {
        if (! RememberConversation::appliesTo($agent)) {
            throw new LogicException('The agent '.$agent::class.' does not remember conversations. Use the RemembersConversations trait to run it as a user.');
        }

        /** @var Agent&RemembersConversations $agent */
        return $agent->forParticipant($this->as);
    }

    protected function remembers(Agent $agent): bool
    {
        if (! RememberConversation::appliesTo($agent)) {
            return false;
        }

        /** @var Agent&RemembersConversations $agent */
        return $agent->hasConversationParticipant() || $agent->currentConversation() !== null;
    }

    protected function conversation(): Agent
    {
        if ($this->conversation === null) {
            throw new LogicException('Start a conversation before prompting the agent.');
        }

        return $this->conversation;
    }
}
