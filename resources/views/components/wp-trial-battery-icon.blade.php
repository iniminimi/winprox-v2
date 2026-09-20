@props([
    'level' => 1,
])

@php
    $level = max(1, min(5, (int) $level));
    $barsFilled = 6 - $level;
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 22" fill="none" aria-hidden="true" {{ $attributes }}>
    <rect x="1.5" y="3.5" width="40" height="15" rx="3.5" stroke="currentColor" stroke-width="2" />
    <rect x="42.5" y="8" width="4" height="6" rx="1.4" fill="currentColor" />
    @for ($i = 0; $i < 5; $i++)
        @if ($i < $barsFilled)
            <rect x="{{ 5.2 + ($i * 7.15) }}" y="6.6" width="5.6" height="8.8" rx="1.1" fill="currentColor" />
        @endif
    @endfor
</svg>
