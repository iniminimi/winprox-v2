@props([
    'src',
    'title' => '',
    'modifier' => '',
])

<div @class(['wp-video', $modifier])>
    <video
        class="wp-video-player"
        controls
        preload="metadata"
        playsinline
        aria-label="{{ $title }}"
    >
        <source src="{{ $src }}" type="video/mp4">
    </video>
</div>
