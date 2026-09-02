@props([
    'worker',
    'size' => 'sm',
    'tone' => 'present',
])

@php
    $url = $worker?->photoPublicUrl();
    $initial = $worker?->initialsLetter() ?? '?';
    $showHoverPreview = filled($url) && $size !== 'lg';
@endphp

<span {{ $attributes->class([
    'wp-worker-avatar',
    'wp-worker-avatar--'.$size,
    'wp-worker-avatar--'.$tone,
    'wp-worker-avatar--photo' => filled($url),
    'wp-worker-avatar--previewable' => $showHoverPreview,
]) }} aria-hidden="true">
    @if (filled($url))
        <img src="{{ $url }}" alt="" class="wp-worker-avatar__img" loading="lazy" decoding="async">
        @if ($showHoverPreview)
            <span class="wp-worker-avatar__zoom">
                <img src="{{ $url }}" alt="" class="wp-worker-avatar__zoom-img" loading="lazy" decoding="async">
            </span>
        @endif
    @else
        {{ $initial }}
    @endif
</span>
