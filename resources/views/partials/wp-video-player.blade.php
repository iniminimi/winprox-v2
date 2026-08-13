@props([
    'src',
    'title' => '',
    'autoplay' => false,
])

<div class="wp-video">
    <video
        class="wp-video-player"
        controls
        @if ($autoplay)
            autoplay
            muted
            preload="auto"
        @else
            preload="metadata"
        @endif
        playsinline
        aria-label="{{ $title }}"
    >
        <source src="{{ $src }}" type="video/mp4">
    </video>
</div>
