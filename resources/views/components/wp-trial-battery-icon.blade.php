@props([
    'level' => 1,
])

@php
    $level = max(1, min(5, (int) $level));
    $barsFilled = 6 - $level;
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 44" fill="none" aria-hidden="true" {{ $attributes }}>
    <rect x="8" y="0" width="8" height="4" rx="1.5" fill="#EAB308" />
    <rect x="4" y="5" width="16" height="38" rx="3" stroke="#EAB308" stroke-width="2" />
    @for ($i = 0; $i < 5; $i++)
        @php
            $barIndex = 4 - $i;
            $y = 9 + ($i * 6.5);
        @endphp
        @if ($barIndex < $barsFilled)
            <rect x="7" y="{{ $y }}" width="10" height="4.5" rx="1" fill="#EAB308" />
        @endif
    @endfor
</svg>
