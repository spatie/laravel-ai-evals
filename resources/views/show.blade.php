<x-ai-tests::layout :title="$result->title()">
    <p><a href="{{ route('ai-tests.index') }}">All results</a></p>

    <h1>{{ $result->title() }}</h1>

    <div class="facts">
        <span class="{{ $result->get('passed') ? 'passed' : 'failed' }}">{{ $result->get('passed') ? 'Passed' : 'Failed' }}</span>
        <span>{{ $result->get('passed_runs') }}/{{ $result->get('runs') }} runs passed (min {{ $result->get('min_pass_rate') }})</span>
        @if ($result->get('model'))
            <span>Model: {{ $result->get('model') }}</span>
        @endif
        @if ($result->get('judge_model'))
            <span>Judge: {{ $result->get('judge_model') }}</span>
        @endif
        @if ($result->get('seeder'))
            <span>Seeder: {{ $result->get('seeder') }}</span>
        @endif
        <span>{{ $result->get('test_file') }}</span>
        <span>{{ $result->get('ran_at') }}</span>
    </div>

    <div class="body">
        {!! $html !!}
    </div>
</x-ai-tests::layout>
