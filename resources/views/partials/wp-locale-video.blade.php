@props([
    'basename',
    'title' => '',
    'suffix' => '_01',
])

@php
    $locale = app()->getLocale();
    $rel = "video/{$locale}/{$basename}_{$locale}{$suffix}.mp4";
@endphp

@if (is_file(public_path($rel)))
    @include('partials.wp-video-player', [
        'src' => asset($rel),
        'title' => $title,
    ])
@endif
