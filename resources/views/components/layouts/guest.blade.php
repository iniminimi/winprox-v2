<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'WinProx' }}</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="wp-shell">
    <div class="wp-auth">
        <div class="wp-auth-shell">
            <div class="wp-auth-top">
                @include('partials.wp-lang-switch', ['variant' => 'inline'])
            </div>

            <div class="wp-auth-brand">
                <span class="wp-auth-logo">WinProx</span>
                <span class="wp-auth-tagline">{{ __('common.brand.tagline') }}</span>
            </div>

            <div class="wp-auth-card wp-card wp-card-pad">
                {{ $slot }}
            </div>

            <div class="wp-auth-footer">
                <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener noreferrer">{{ __('common.nav.legal') }}</a>
                <span class="wp-muted">&middot;</span>
                <a href="{{ route('contact.index') }}">{{ __('common.nav.contact') }}</a>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
