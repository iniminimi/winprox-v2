@props([
    'as' => 'h2',
    'lines' => [],
    'accent' => [],
    'text' => null,
])

@php
    $accentLookup = array_fill_keys($accent, true);
    $revealLines = $lines !== [] ? $lines : ($text !== null && $text !== '' ? [preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY)] : []);
    $wordIndex = 0;
@endphp

<{{ $as }} {{ $attributes->merge(['class' => 'wp-text-reveal', 'data-wp-text-reveal' => true]) }}>
    @foreach ($revealLines as $line)
        <span class="wp-text-reveal__line">
            @foreach ($line as $word)
                <span class="wp-text-reveal__word" style="--wp-reveal-i: {{ $wordIndex }}">
                    @if (isset($accentLookup[$word]))
                        <em class="wp-text-reveal__accent">{{ $word }}</em>
                    @else
                        {{ $word }}
                    @endif
                </span>@php $wordIndex++; @endphp
            @endforeach
        </span>
    @endforeach
</{{ $as }}>
