<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? __('error.default.title') }} — WinProx</title>
    @vite(['resources/css/app.css'])
</head>
<body class="wp-error-body">
@php
    $supported = config('locales.supported', []);
    $default   = config('locales.default', config('app.locale'));
    $sessionLocale = session('locale');
    $userLocale    = auth()->user()?->locale;

    if (is_string($sessionLocale) && in_array($sessionLocale, $supported, true)) {
        $locale = $sessionLocale;
    } elseif (is_string($userLocale) && in_array($userLocale, $supported, true)) {
        $locale = $userLocale;
    } else {
        $locale = $default;
    }

    if (! in_array($locale, $supported, true)) {
        $locale = $default;
    }

    app()->setLocale($locale);

    $randomImage = '/images/error/error_' . random_int(1, 10) . '.jpg';
@endphp

<main class="wp-error-layout">
    <div class="wp-error-card wp-card wp-card-pad wp-stack wp-error-stack">
        <img
            src="{{ $randomImage }}"
            alt=""
            class="wp-error-image"
            loading="lazy"
        >

        <span class="wp-pill">{{ $code ?? __('error.default.code') }}</span>

        <h1 class="wp-error-title">{{ $title ?? __('error.default.title') }}</h1>
        <p class="wp-error-message">{{ $message ?? __('error.default.message') }}</p>

        <div class="wp-error-actions">
            @if(!empty($primaryAction))
                <a
                    class="btn btn--primary"
                    href="{{ $primaryAction['url'] ?? '#' }}"
                    @if(!empty($primaryAction['onclick'])) onclick="{{ $primaryAction['onclick'] }}" @endif
                >
                    {{ $primaryAction['label'] ?? __('error.action.home') }}
                </a>
            @endif
            @if(!empty($secondaryAction))
                <a
                    class="btn btn--ghost"
                    href="{{ $secondaryAction['url'] ?? '#' }}"
                    @if(!empty($secondaryAction['onclick'])) onclick="{{ $secondaryAction['onclick'] }}" @endif
                >
                    {{ $secondaryAction['label'] ?? __('error.action.back') }}
                </a>
            @endif
        </div>
    </div>
</main>
</body>
</html>
