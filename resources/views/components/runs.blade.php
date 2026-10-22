@props(['passed', 'runs'])

@php
    $hasCounts = is_numeric($passed) && is_numeric($runs);
    $status = match (true) {
        ! $hasCounts => 'unknown',
        (int) $passed === (int) $runs => 'passed',
        (int) $passed === 0 => 'failed',
        default => 'partial',
    };
@endphp

<span {{ $attributes->class(['runs', "runs-{$status}"]) }}>
    @if ($hasCounts && $runs > 0 && $runs <= 10)
        <span class="meter" aria-hidden="true">
            @for ($run = 1; $run <= $runs; $run++)
                <span @class(['is-filled' => $run <= $passed])></span>
            @endfor
        </span>
    @endif
    <span class="runs-count">{{ $hasCounts ? "{$passed}/{$runs}" : '–' }}</span>
</span>
