<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="wp-date-locale" content="{{ \App\Support\Translation\LocaleSupport::dateInputLang() }}">
    <title>{{ $title ?? __('welcome.meta_title') }}</title>
    @include('partials.social-meta', [
        'title' => $socialTitle ?? __('welcome.social.og_title'),
        'description' => $socialDescription ?? __('welcome.social.og_description'),
        'url' => $socialUrl ?? url()->current(),
    ])
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="wp-shell wp-welcome-shell">
    <div class="wp-welcome-top">
        @include('partials.wp-welcome-nav')
    </div>

    <main class="wp-welcome-marketing-main">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
