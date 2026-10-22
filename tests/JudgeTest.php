<?php

use Laravel\Ai\Prompts\AgentPrompt;
use Spatie\LaravelAiTests\Judge\Judge;
use Spatie\LaravelAiTests\Judge\JudgeAgent;
use Spatie\LaravelAiTests\Results\CheckOutcome;
use Spatie\LaravelAiTests\Transcript\Transcript;

it('matches verdicts to criteria by number', function () {
    JudgeAgent::fake([
        ['verdicts' => [
            ['criterion' => 2, 'reason' => 'It is rude.', 'pass' => false],
            ['criterion' => 1, 'reason' => 'It names the order.', 'pass' => true],
        ]],
    ]);

    $transcript = new Transcript('Where is my order?');
    $transcript->texts[] = 'Order 1234 shipped.';

    $result = app(Judge::class)->evaluate(['Names the order.', 'Is polite.', 'Gives a date.'], $transcript);

    expect(array_map(fn (CheckOutcome $outcome) => [$outcome->label, $outcome->passed, $outcome->reason], $result->outcomes))->toBe([
        ['Names the order.', true, 'It names the order.'],
        ['Is polite.', false, 'It is rude.'],
        ['Gives a date.', false, 'The judge returned no verdict.'],
    ]);

    JudgeAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains($prompt->prompt, "Assistant answer:\nOrder 1234 shipped.")
        && str_contains($prompt->prompt, "1. Names the order.\n2. Is polite.\n3. Gives a date."));
});

it('does not call the judge without a rubric', function () {
    JudgeAgent::fake();

    $result = app(Judge::class)->evaluate([], new Transcript('Hi'));

    expect($result->outcomes)->toBe([]);

    JudgeAgent::assertNeverPrompted();
});
