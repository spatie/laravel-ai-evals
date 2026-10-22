@props(['value'])

@if ($value)
    @php($local = $value->setTimezone(config('app.timezone')))
    <time datetime="{{ $value->toIso8601String() }}" {{ $attributes->class('numeric') }}>{{ $local->format('j M Y, H:i') }} <span class="muted">{{ $local->format('T') }}</span></time>
@else
    <span {{ $attributes->class('muted') }}>–</span>
@endif
