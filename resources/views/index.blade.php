@php
    $failedCount = $results->filter(fn ($result) => $result->status() === 'failed')->count();
    $passedCount = $results->filter(fn ($result) => $result->status() === 'passed')->count();
    $lastRun = $results->map(fn ($result) => $result->ranAt())->filter()->max();
    $usualMinPassRate = $results->map(fn ($result) => $result->percentage('min_pass_rate'))->filter()->countBy()->sortDesc()->keys()->first();
@endphp

<x-ai-tests::layout title="AI test results">
    @push('styles')
        <style>
            .page-header { margin-bottom: 24px; }
            .summary { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 16px; margin: 8px 0 0; color: var(--muted); font-size: 14px; }
            .summary strong { font-weight: 600; }
            .summary .is-failed { color: var(--failed); }
            .summary .is-passed { color: var(--passed); }
            .results td { vertical-align: middle; }
            .results tbody tr { position: relative; }
            .results tbody tr:hover { background: var(--surface); }
            .results .test a { color: var(--text); font-weight: 500; }
            .results .test a::after { content: ""; position: absolute; inset: 0; }
            .results tbody tr:hover .test a { color: var(--link); }
            .results .test a:focus-visible { outline: none; }
            .results tbody tr:has(a:focus-visible) { outline: 2px solid var(--focus); outline-offset: -2px; }
            .results .model, .results .ran-at { color: var(--muted); font-size: 13.5px; white-space: nowrap; }
            .results .threshold { display: block; color: var(--muted); font-size: 12.5px; }
            .empty { padding: 32px 0; border-top: 1px solid var(--border); color: var(--muted); }
            .empty p { margin: 0 0 4px; }
            .empty strong { color: var(--text); font-weight: 600; }
            @media (max-width: 720px) {
                .results .column-model, .results .column-ran-at { display: none; }
            }
            @media (max-width: 480px) {
                .results .meter { display: none; }
                .results th, .results td { padding-left: 8px; padding-right: 8px; }
                .results th:first-child, .results td:first-child { padding-left: 0; }
                .results th:last-child, .results td:last-child { padding-right: 0; }
            }
        </style>
    @endpush

    <header class="page-header">
        <h1>AI test results</h1>

        @if ($results->isNotEmpty())
            <p class="summary">
                @if ($failedCount > 0)
                    <strong class="is-failed">{{ $failedCount }} failing</strong>
                @endif
                <strong class="is-passed">{{ $passedCount }} passing</strong>
                <span>{{ $results->count() }} {{ str('test')->plural($results->count()) }}</span>
                @if ($usualMinPassRate)
                    <span>Min pass rate {{ $usualMinPassRate }}</span>
                @endif
                @if ($lastRun)
                    <span>Last run <x-ai-tests::time :value="$lastRun" /></span>
                @endif
            </p>
        @endif
    </header>

    @if ($results->isEmpty())
        <div class="empty">
            <p><strong>No results yet.</strong></p>
            <p>Run your AI tests and their results will show up here. Results are read from <code>{{ $resultsPath }}</code>.</p>
        </div>
    @else
        <div class="table-scroll">
            <table class="results">
                <thead>
                    <tr>
                        <th scope="col">Status</th>
                        <th scope="col">Test</th>
                        <th scope="col">Runs passed</th>
                        <th scope="col" class="column-model">Model</th>
                        <th scope="col" class="column-ran-at">Ran at</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($results as $result)
                        <tr>
                            <td><x-ai-tests::status :status="$result->status()" /></td>
                            <td class="test"><a href="{{ route('ai-tests.show', $result->slug) }}">{{ $result->title() }}</a></td>
                            <td>
                                <x-ai-tests::runs :passed="$result->get('passed_runs')" :runs="$result->get('runs')" />
                                @if ($result->percentage('min_pass_rate') && $result->percentage('min_pass_rate') !== $usualMinPassRate)
                                    <span class="threshold numeric">min {{ $result->percentage('min_pass_rate') }}</span>
                                @endif
                            </td>
                            <td class="model column-model">{{ $result->get('model') ?? '–' }}</td>
                            <td class="ran-at column-ran-at"><x-ai-tests::time :value="$result->ranAt()" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ai-tests::layout>
