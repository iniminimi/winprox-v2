@php
    $src = $src ?? null;
    $alt = $alt ?? '';
    $eager = (bool) ($eager ?? false);
    $modifier = $modifier ?? '';
@endphp
@if (filled($src))
    <figure class="wp-welcome-screenshot wp-welcome-screenshot--desktop wp-landing-visual {{ $modifier }}">
        <img
            src="{{ asset($src) }}"
            alt="{{ $alt }}"
            class="wp-welcome-screenshot__img"
            @if ($eager) fetchpriority="high" @else loading="lazy" @endif
            decoding="async"
        >
    </figure>
@endif
