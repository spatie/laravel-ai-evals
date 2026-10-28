@props(['status'])

<span {{ $attributes->class(['status', "status-{$status->value}"]) }}>{{ $status->label() }}</span>
