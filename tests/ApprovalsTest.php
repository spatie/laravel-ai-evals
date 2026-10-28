<?php

use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Spatie\LaravelAiEvals\Evaluation;
use Spatie\LaravelAiEvals\Tests\TestSupport\ShopDriver;
use Spatie\LaravelAiEvals\Tests\TestSupport\SupportAgent;
use Spatie\LaravelAiEvals\Transcript\Transcript;

beforeEach(function () {
    SupportAgent::fake([
        AgentResponse::fakeWithPendingApprovals([new PendingApproval('call-1', 'cancel_order', ['order' => '1234'])]),
        'Done.',
    ]);
});

it('rejects pending tool calls by default', function () {
    $evaluation = new Evaluation('asks before cancelling');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Cancel order 1234.')
        ->toPassCheck('Records the rejection', fn (Transcript $transcript) => $transcript->toolCalls['call-1']->decisionLabel() === 'rejected');

    $evaluation->execute();

    SupportAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->approvalDecisions?->get('call-1')?->isRejected() === true);
});

it('approves pending tool calls with approveAll', function () {
    $evaluation = new Evaluation('cancels after approval');

    $evaluation
        ->agent(fn () => ShopDriver::forNewCustomer())
        ->prompt('Cancel order 1234.')
        ->approveAll()
        ->toPassCheck('Records the approval', fn (Transcript $transcript) => $transcript->toolCalls['call-1']->decisionLabel() === 'approved')
        ->toContain('Done');

    $evaluation->execute();

    SupportAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->approvalDecisions?->get('call-1')?->isApproved() === true);
});
