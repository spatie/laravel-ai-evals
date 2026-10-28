<?php

namespace Spatie\LaravelAiEvals\Transcript;

use Closure;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;
use Spatie\LaravelAiEvals\ToolName;

class Transcript
{
    /** @var array<string, ToolCallRecord> */
    public array $toolCalls = [];

    /** @var array<int, string> */
    public array $texts = [];

    public function __construct(
        public string $prompt,
    ) {}

    public function addResponse(AgentResponse $response): void
    {
        foreach ($response->toolCalls as $toolCall) {
            $this->recordToolCall($toolCall);
        }

        foreach ($response->toolResults as $toolResult) {
            $this->recordToolResult($toolResult);
        }

        foreach ($response->pendingApprovals as $pendingApproval) {
            $this->recordPendingApproval($pendingApproval);
        }

        $text = trim($response->text);

        if ($text !== '') {
            $this->texts[] = $text;
        }
    }

    /** @param array<string, bool> $decisions */
    public function recordDecisions(array $decisions): void
    {
        foreach ($decisions as $toolCallId => $approved) {
            $this->toolCalls[$toolCallId]->approved = $approved;
        }
    }

    /**
     * @param  array<string, mixed>|Closure(array<string, mixed>): mixed|null  $arguments
     * @return array<string, ToolCallRecord>
     */
    public function callsTo(string $tool, array|Closure|null $arguments = null): array
    {
        $name = ToolName::of($tool);

        return array_filter(
            $this->toolCalls,
            fn (ToolCallRecord $toolCall): bool => $toolCall->name === $name && $toolCall->hasArguments($arguments),
        );
    }

    public function answer(): string
    {
        return implode("\n\n", $this->texts);
    }

    public function wordCount(): int
    {
        return Str::of($this->answer())
            ->split('/\s+/u')
            ->filter(fn (string $word): bool => preg_match('/[\pL\pN]/u', $word) === 1)
            ->count();
    }

    protected function recordToolCall(ToolCall $toolCall): void
    {
        $this->toolCalls[$toolCall->id] ??= new ToolCallRecord($toolCall->name, $toolCall->arguments);
    }

    protected function recordToolResult(ToolResult $toolResult): void
    {
        $record = $this->toolCalls[$toolResult->id] ??= new ToolCallRecord($toolResult->name, $toolResult->arguments);

        $record->result = Str::limit($toolResult->text(), 15000);

        $record->status = match (true) {
            $toolResult->denied => ToolCallStatus::Rejected,
            $toolResult->failed => ToolCallStatus::Failed,
            default => ToolCallStatus::Completed,
        };
    }

    protected function recordPendingApproval(PendingApproval $pendingApproval): void
    {
        $record = $this->toolCalls[$pendingApproval->id] ??= new ToolCallRecord($pendingApproval->tool, $pendingApproval->arguments);

        $record->status = ToolCallStatus::Pending;
    }
}
