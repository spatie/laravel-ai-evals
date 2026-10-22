<?php

use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Tests\TestSupport\ShopSeeder;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

beforeEach(function () {
    SupportAgent::fake([
        AgentResponse::fakeWithPendingApprovals([new PendingApproval('call-1', 'cancel_order', ['order' => '1234'])]),
        'Done.',
    ]);
});

it('rejects pending tool calls by default', function () {
    $aiTest = makeAiTest('asks before cancelling');

    $aiTest
        ->seed(ShopSeeder::class)
        ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->user))
        ->runs(1)
        ->user('Cancel order 1234.');

    $aiTest->execute();

    SupportAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->approvalDecisions?->get('call-1')?->isRejected() === true);

    expect(resultFile('asks-before-cancelling')->body)->toContain('Tools: `cancel_order` (order: "1234", rejected)');
});

it('approves pending tool calls with approveAll', function () {
    $aiTest = makeAiTest('cancels after approval');

    $aiTest
        ->seed(ShopSeeder::class)
        ->driver(fn (ShopSeeder $seeder) => new AgentDriver(SupportAgent::class, as: $seeder->user))
        ->runs(1)
        ->user('Cancel order 1234.')
        ->approveAll();

    $aiTest->execute();

    SupportAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->approvalDecisions?->get('call-1')?->isApproved() === true);

    expect(resultFile('cancels-after-approval')->body)
        ->toContain('Tools: `cancel_order` (order: "1234", approved)')
        ->toContain('Done.');
});
