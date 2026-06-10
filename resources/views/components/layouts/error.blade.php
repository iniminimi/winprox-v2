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
    $randomImage = '/images/error/error_' . random_int(1, 10) . '.jpg';
@endphp

<main class="wp-error-layout">
    <div class="wp-error-card wp-card wp-card-pad wp-stack" style="--wp-stack-gap: 1rem;">
        <img
            src="{{ $randomImage }}"
            alt=""
            class="wp-error-image"
            loading="lazy"
        >

        <span class="wp-error-code">{{ $code ?? __('error.default.code') }}</span>

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
