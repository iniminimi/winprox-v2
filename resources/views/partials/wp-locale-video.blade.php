@props([
    'basename',
    'title' => '',
])

@php
    $locale = app()->getLocale();
    $rel = "video/{$locale}/{$basename}_{$locale}_01.mp4";
@endphp

@if (is_file(public_path($rel)))
    @include('partials.wp-video-player', [
        'src' => asset($rel),
        'title' => $title,
    ])
@endif
