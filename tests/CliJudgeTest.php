<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Spatie\LaravelAiTests\Drivers\AgentDriver;
use Spatie\LaravelAiTests\Judge\JudgeAgent;
use Spatie\LaravelAiTests\Tests\TestSupport\SupportAgent;

beforeEach(function () {
    SupportAgent::fake(['Order 1234 shipped.']);
});

function judgedOrderTest(string $description): void
{
    $aiTest = makeAiTest($description);

    $aiTest
        ->driver(new AgentDriver(SupportAgent::class))
        ->runs(1)
        ->user('Where is my order?')
        ->expectRubric('Names the order.', 'Is polite.');

    rescue(fn () => $aiTest->execute(), report: false);
}

function claudeOutput(mixed $structuredOutput): string
{
    return json_encode([
        'type' => 'result',
        'is_error' => false,
        'structured_output' => $structuredOutput,
        'modelUsage' => ['claude-sonnet-5-5' => ['outputTokens' => 120]],
    ]);
}

it('judges with Claude Code using the instructions and schema of the judge agent', function () {
    config()->set('ai-tests.judge.runner', 'claude');
    config()->set('ai-tests.judge.claude.model', 'sonnet');

    Process::fake([
        '*' => Process::result(output: claudeOutput(['verdicts' => [
            ['criterion' => 1, 'reason' => 'It names order 1234.', 'pass' => true],
            ['criterion' => 2, 'reason' => 'It is curt.', 'pass' => false],
        ]])),
    ]);

    judgedOrderTest('judges with claude');

    $result = resultFile('judges-with-claude');

    expect($result->get('judge_model'))->toBe('claude-sonnet-5-5 via Claude Code');

    expect($result->get('turns')[0]['runs'][0]['verdicts'])->toBe([
        ['check' => 'Names the order.', 'passed' => true, 'reason' => 'It names order 1234.'],
        ['check' => 'Is polite.', 'passed' => false, 'reason' => 'It is curt.'],
    ]);

    Process::assertRan(function (PendingProcess $process): bool {
        $command = implode(' ', $process->command);

        return str_ends_with($process->path, '/workspace')
            && str_contains($process->input, "1. Names the order.\n2. Is polite.")
            && in_array((new JudgeAgent)->instructions(), $process->command, true)
            && str_contains($command, '--json-schema {"additionalProperties":false,"properties":{"verdicts"')
            && str_contains($command, '--tools  --strict-mcp-config --setting-sources  --safe-mode')
            && str_ends_with($command, '--model sonnet');
    });
});

it('judges with Codex using the instructions and schema of the judge agent', function () {
    config()->set('ai-tests.judge.runner', 'codex');

    $instructions = null;

    Process::fake(function (PendingProcess $process) use (&$instructions) {
        $option = fn (string $name): string => $process->command[array_search($name, $process->command) + 1];

        $instructions = File::get(Str::between($option('--config'), 'model_instructions_file="', '"'));

        File::put($option('--output-last-message'), json_encode(['verdicts' => [
            ['criterion' => 1, 'reason' => 'It names order 1234.', 'pass' => true],
            ['criterion' => 2, 'reason' => 'It says thanks.', 'pass' => true],
        ]]));

        return Process::result(errorOutput: "workdir: /tmp\nmodel: gpt-6-astra\nprovider: openai\n");
    });

    judgedOrderTest('judges with codex');

    $result = resultFile('judges-with-codex');

    expect($result->get('passed'))->toBeTrue();
    expect($result->get('judge_model'))->toBe('gpt-6-astra via Codex');
    expect($instructions)->toBe((new JudgeAgent)->instructions());

    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'codex'
        && $process->command[1] === 'exec'
        && in_array('--ignore-user-config', $process->command, true)
        && str_contains($process->input, "1. Names the order.\n2. Is polite."));
});

it('records a failing or invalid judge CLI run as a judge error', function (mixed $processResult, string $judgeError) {
    config()->set('ai-tests.judge.runner', 'claude');

    Process::fake(['*' => $processResult]);

    judgedOrderTest('cannot be judged by claude');

    $result = resultFile('cannot-be-judged-by-claude');

    expect($result->get('unjudged_runs'))->toBe(1);
    expect($result->get('turns')[0]['runs'][0]['judge_error'])->toStartWith($judgeError);
})->with([
    'non-zero exit' => [
        fn () => Process::result(errorOutput: 'Not logged in. Run claude auth login.', exitCode: 1),
        'RuntimeException: Claude Code exited with code 1: Not logged in. Run claude auth login.',
    ],
    'output without verdicts' => [
        fn () => Process::result(output: claudeOutput(['verdicts' => [['criterion' => 'first']]])),
        'RuntimeException: Claude Code returned output that does not match the judge schema',
    ],
]);
