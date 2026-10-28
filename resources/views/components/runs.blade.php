@props(['passed', 'runs'])

@use('Spatie\LaravelAiTests\Viewer\Status')

@php($status = Status::fromCounts($passed, $runs))

<span {{ $attributes->class(['runs', "runs-{$status->value}"]) }}>
    @if ($status !== Status::Unknown && $runs <= 10)
        <span class="meter" aria-hidden="true">
            @for ($run = 1; $run <= $runs; $run++)
                <span @class(['is-filled' => $run <= $passed])></span>
            @endfor
        </span>
    @endif
    <span class="runs-count">{{ $status !== Status::Unknown ? "{$passed}/{$runs}" : '–' }}</span>
</span>
