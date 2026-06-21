<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" translate="no" data-theme="{{ $uiTheme ?? 'simple' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'WinProx' }}</title>
    @include('partials.social-meta')
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="wp-shell wp-auth-body">
    <div class="wp-auth">
        <div class="wp-auth-card wp-card">
            <div class="wp-auth-top">
                @include('partials.wp-lang-switch', ['variant' => 'inline'])
            </div>

            <a href="{{ route('welcome') }}" class="wp-auth-logo-link" aria-label="{{ __('welcome.back_home') }}">
                @if (file_exists(public_path('images/Winprox_logo_200.png')))
                    <img src="{{ asset('images/Winprox_logo_200.png') }}" alt="" class="wp-auth-logo-img" width="180" height="auto" />
                @elseif (file_exists(public_path('images/Winprox_logo_300.png')))
                    <img src="{{ asset('images/Winprox_logo_300.png') }}" alt="" class="wp-auth-logo-img" width="120" height="120" />
                @else
                    <span class="wp-auth-logo">WinProx</span>
                @endif
                <span class="wp-auth-tagline">{{ __('common.brand.tagline') }}</span>
            </a>

            <div class="wp-auth-content">
                {{ $slot }}
            </div>

            @include('partials.wp-auth-legal-links')

            <p class="wp-auth-copyright">&copy; {{ date('Y') }} WinProx</p>
        </div>
    </div>

    @livewireScripts
</body>
</html>
