@props(['status'])

<span {{ $attributes->class(['status', "status-{$status}"]) }}>{{ match ($status) { 'passed' => 'Passed', 'failed' => 'Failed', 'unjudged' => 'Unjudged', default => 'Unknown' } }}</span>
