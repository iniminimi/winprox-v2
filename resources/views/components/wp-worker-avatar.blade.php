@props([
    'worker',
    'size' => 'sm',
    'tone' => 'present',
])

@php
    $url = $worker?->photoPublicUrl();
    $initial = $worker?->initialsLetter() ?? '?';
@endphp

<span {{ $attributes->class([
    'wp-worker-avatar',
    'wp-worker-avatar--'.$size,
    'wp-worker-avatar--'.$tone,
    'wp-worker-avatar--photo' => filled($url),
]) }} aria-hidden="true">
    @if (filled($url))
        <img src="{{ $url }}" alt="" class="wp-worker-avatar__img" loading="lazy" decoding="async">
    @else
        {{ $initial }}
    @endif
</span>
