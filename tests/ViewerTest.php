<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->app['env'] = 'local';

    File::ensureDirectoryExists(config('ai-tests.results_path'));

    File::put(config('ai-tests.results_path').'/answers-briefly.md', <<<'MD'
    ---
    slug: answers-briefly
    title: 'Answers briefly'
    model: gpt-test
    runs: 3
    passed_runs: 2
    pass_rate: 0.67
    min_pass_rate: 0.66
    passed: true
    ran_at: '2026-10-22T10:00:00+00:00'
    ---

    # Answers briefly

    ## Turn 1

    | Check | Passed |
    | --- | --- |
    | At most 5 words | 2/3 |

    <script>alert('answer')</script>
    MD);
});

afterEach(function () {
    $this->app['env'] = 'testing';
});

it('lists the result files', function () {
    $this->get('/ai-tests')
        ->assertOk()
        ->assertSee('Answers briefly')
        ->assertSee('gpt-test')
        ->assertSee('/ai-tests/answers-briefly');
});

it('renders a result file', function () {
    $this->get('/ai-tests/answers-briefly')
        ->assertOk()
        ->assertSee('2 of 3 runs passed')
        ->assertSee('<td>At most 5 words</td>', escape: false)
        ->assertDontSee('<script>', escape: false);
});

it('groups judge notes by check and shows the example answer with its tool calls', function () {
    File::put(config('ai-tests.results_path').'/finds-a-log-line.md', <<<'MD'
    ---
    title: 'Finds a log line'
    runs: 2
    passed_runs: 0
    passed: false
    ---

    ## Turn 1

    **User:** Find the sync failure

    | Check | Passed |
    | --- | --- |
    | Mentions the recovery afterwards | 0/2 |

    **Judge notes**

    - Run 1, "Mentions the recovery afterwards": failed. No retry mentioned.
    - Run 2, "Mentions the recovery afterwards": failed. No completion log mentioned.

    **Crashes**

    - Run 3: RuntimeException: Connection reset

    **Example answer** (run 1, failed)

    Tools: `list_logs` (search: "sync failed", limit: 100), `get_error`

    Found **one** log line.
    MD);

    $this->get('/ai-tests/finds-a-log-line')
        ->assertOk()
        ->assertSeeInOrder([
            'Mentions the recovery afterwards',
            'Judge notes',
            'Mentions the recovery afterwards',
            'Run 1',
            'No retry mentioned.',
            'Run 2',
            'No completion log mentioned.',
            'Crashes',
            'RuntimeException: Connection reset',
            'Example answer',
            'list_logs',
            'search: &quot;sync failed&quot;, limit: 100',
            'get_error',
            '<strong>one</strong>',
        ], escape: false);
});

it('returns a 404 for unknown or unsafe slugs', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    '/ai-tests/missing',
    '/ai-tests/..%2F..%2F.env',
    '/ai-tests/Answers-Briefly',
]);

it('returns a 404 outside the local environment', function () {
    $this->app['env'] = 'production';

    $this->get('/ai-tests')->assertNotFound();
});
