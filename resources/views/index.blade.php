<x-ai-tests::layout title="AI test results">
    <h1>AI test results</h1>

    @if ($results->isEmpty())
        <p class="muted">No results yet. Run your AI tests to create them.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Result</th>
                    <th>Pass rate</th>
                    <th>Runs</th>
                    <th>Model</th>
                    <th>Ran at</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($results as $result)
                    <tr>
                        <td><a href="{{ route('ai-tests.show', $result->slug) }}">{{ $result->title() }}</a></td>
                        <td class="{{ $result->get('passed') ? 'passed' : 'failed' }}">{{ $result->get('passed') ? 'Passed' : 'Failed' }}</td>
                        <td>{{ $result->get('pass_rate') }} <span class="muted">(min {{ $result->get('min_pass_rate') }})</span></td>
                        <td>{{ $result->get('passed_runs') }}/{{ $result->get('runs') }}</td>
                        <td>{{ $result->get('model') }}</td>
                        <td>{{ $result->get('ran_at') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-ai-tests::layout>
