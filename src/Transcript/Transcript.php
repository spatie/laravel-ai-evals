<?php

namespace Spatie\LaravelAiTests\Transcript;

use Illuminate\Support\Str;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\ToolResult;

class Transcript
{
    /** @var array<string, ToolCallRecord> */
    public array $toolCalls = [];

    /** @var array<int, string> */
    public array $texts = [];

    public ?string $model = null;

    public function __construct(
        public string $prompt,
    ) {}

    public function addResponse(AgentResponse $response): void
    {
        $this->model ??= $response->meta->model;

        foreach ($response->toolCalls as $toolCall) {
            $this->recordToolCall($toolCall);
        }

        foreach ($response->toolResults as $toolResult) {
            $this->recordToolResult($toolResult);
        }

        foreach ($response->pendingApprovals as $pendingApproval) {
            $this->recordPendingApproval($pendingApproval);
        }

        if (trim($response->text) !== '') {
            $this->texts[] = trim($response->text);
        }
    }

    /** @param array<string, bool> $decisions */
    public function recordDecisions(array $decisions): void
    {
        foreach ($decisions as $toolCallId => $approved) {
            if (isset($this->toolCalls[$toolCallId])) {
                $this->toolCalls[$toolCallId]->approved = $approved;
            }
        }
    }

    /** @return array<int, string> */
    public function toolNames(): array
    {
        return array_values(array_map(
            fn (ToolCallRecord $toolCall): string => $toolCall->name,
            $this->toolCalls,
        ));
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

    public function forJudge(): string
    {
        $lines = ["User: {$this->prompt}", ''];

        foreach ($this->toolCalls as $toolCall) {
            $lines[] = $this->toolCallForJudge($toolCall);
            $lines[] = '';
        }

        $lines[] = 'Assistant answer:';
        $lines[] = $this->answer() !== '' ? $this->answer() : '(no text)';

        return implode("\n", $lines);
    }

    protected function toolCallForJudge(ToolCallRecord $toolCall): string
    {
        $arguments = json_encode($toolCall->arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $decision = $toolCall->decisionLabel() !== null ? ", user {$toolCall->decisionLabel()} it" : '';

        return implode("\n", [
            "Tool call `{$toolCall->name}` {$arguments} ({$toolCall->status->value}{$decision})",
            'Result: '.($toolCall->result ?? '(none)'),
        ]);
    }

    protected function recordToolCall(ToolCall $toolCall): void
    {
        $this->toolCalls[$toolCall->id] ??= new ToolCallRecord($toolCall->id, $toolCall->name, $toolCall->arguments);
    }

    protected function recordToolResult(ToolResult $toolResult): void
    {
        $record = $this->toolCalls[$toolResult->id] ??= new ToolCallRecord($toolResult->id, $toolResult->name, $toolResult->arguments);

        $record->result = Str::limit($toolResult->text(), 15000);

        $record->status = match (true) {
            $toolResult->denied => ToolCallStatus::Rejected,
            $toolResult->failed => ToolCallStatus::Failed,
            default => ToolCallStatus::Completed,
        };
    }

    protected function recordPendingApproval(PendingApproval $pendingApproval): void
    {
        $record = $this->toolCalls[$pendingApproval->id] ??= new ToolCallRecord($pendingApproval->id, $pendingApproval->tool, $pendingApproval->arguments);

        $record->status = ToolCallStatus::Pending;
    }
}
