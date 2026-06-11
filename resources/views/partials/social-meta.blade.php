@props([
    'title' => null,
    'description' => null,
    'image' => null,
    'imageWidth' => null,
    'imageHeight' => null,
    'imageType' => null,
    'url' => null,
    'type' => 'website',
    'ogContext' => 'site',
])

@php
    use App\Support\Marketing\PromoOgImage;

    $socialTitle = $title ?? __('common.social.og_title');
    $socialDescription = $description ?? __('common.social.og_description');
    $promoOg = $ogContext === 'portal' ? PromoOgImage::forPortal() : PromoOgImage::forSite();
    $socialImage = $image ?? $promoOg['url'];
    $socialImageWidth = $imageWidth ?? $promoOg['width'];
    $socialImageHeight = $imageHeight ?? $promoOg['height'];
    $socialImageType = $imageType ?? $promoOg['type'];
    $socialUrl = $url ?? url()->current();
@endphp

<meta name="description" content="{{ $socialDescription }}">
<meta property="og:title" content="{{ $socialTitle }}">
<meta property="og:description" content="{{ $socialDescription }}">
<meta property="og:site_name" content="WinProx">
<meta property="og:image" content="{{ $socialImage }}">
<meta property="og:image:width" content="{{ $socialImageWidth }}">
<meta property="og:image:height" content="{{ $socialImageHeight }}">
<meta property="og:image:type" content="{{ $socialImageType }}">
<meta property="og:image:alt" content="{{ $socialTitle }}">
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
