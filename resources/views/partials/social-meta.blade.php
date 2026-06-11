@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'imageWidth' => 1200,
    'imageHeight' => 896,
    'url' => null,
    'type' => 'website',
])

@php
    $socialTitle = $title ?? __('common.social.og_title');
    $socialDescription = $description ?? __('common.social.og_description');
    $socialImage = $image ?? asset('images/promo/image_worker.png');
    $socialUrl = $url ?? url()->current();
@endphp

<meta name="description" content="{{ $socialDescription }}">
<meta property="og:title" content="{{ $socialTitle }}">
<meta property="og:description" content="{{ $socialDescription }}">
<meta property="og:image" content="{{ $socialImage }}">
<meta property="og:image:width" content="{{ $imageWidth }}">
<meta property="og:image:height" content="{{ $imageHeight }}">
<meta property="og:image:type" content="image/png">
@if (str_starts_with($socialImage, 'https://'))
    <meta property="og:image:secure_url" content="{{ $socialImage }}">
@endif
<meta property="og:url" content="{{ $socialUrl }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $socialTitle }}">
<meta name="twitter:description" content="{{ $socialDescription }}">
<meta name="twitter:image" content="{{ $socialImage }}">
