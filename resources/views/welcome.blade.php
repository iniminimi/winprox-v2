@php
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('welcome.meta_title') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell wp-welcome-shell">
    <header class="wp-welcome-header">
        <a href="{{ route('login') }}" class="wp-brand">WinProx</a>
        <div class="wp-welcome-header-actions">
            @include('partials.wp-lang-switch', ['variant' => 'inline'])
            <a href="{{ route('login') }}" class="btn btn--ghost btn--sm">{{ __('welcome.login') }}</a>
            <a href="{{ route('register') }}" class="btn btn--primary btn--sm">{{ __('welcome.register') }}</a>
        </div>
    </header>

    <main class="wp-welcome">
        <section class="wp-welcome-hero">
            <h1 class="wp-welcome-title">{{ __('welcome.hero.title') }}</h1>
            <p class="wp-welcome-lead">{{ __('welcome.hero.lead') }}</p>
            <div class="wp-welcome-cta">
                <a href="{{ route('register') }}" class="btn btn--primary">{{ __('welcome.hero.cta_register') }}</a>
                <a href="{{ route('login') }}" class="btn btn--ghost">{{ __('welcome.hero.cta_login') }}</a>
            </div>
        </section>

        <section class="wp-welcome-flow" aria-labelledby="welcome-flow-title">
            <h2 id="welcome-flow-title" class="wp-welcome-section-title">{{ __('welcome.flow.title') }}</h2>
            <ol class="wp-welcome-steps">
                @foreach (__('welcome.flow.steps') as $index => $step)
                    <li class="wp-welcome-step">
                        <span class="wp-welcome-step-num">{{ $index + 1 }}</span>
                        <div>
                            <h3>{{ $step['title'] ?? '' }}</h3>
                            <p class="wp-muted">{{ $step['body'] ?? '' }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <div class="wp-welcome-qr-art" aria-hidden="true">
                <div class="wp-welcome-qr-grid"></div>
                <span class="wp-welcome-qr-label">{{ __('welcome.qr_illustration_label') }}</span>
            </div>
        </section>

        <section class="wp-welcome-footer-links">
            <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">{{ __('legal.documents.privacy') }}</a>
            <a href="{{ route('contact.index') }}">{{ __('contact.title') }}</a>
        </section>
    </main>
</body>
</html>
