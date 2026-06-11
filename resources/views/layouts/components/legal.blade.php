<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" data-theme="{{ $uiTheme ?? 'simple' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — WinProx</title>
    @include('partials.social-meta', ['title' => $title . ' — WinProx'])
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell wp-legal-doc">
    <header class="wp-legal-topbar">
        <nav class="wp-legal-topnav" aria-label="{{ __('legal.nav_label') }}">
            <a href="{{ route('welcome') }}" class="wp-legal-topnav-link">{{ __('legal.back_home') }}</a>
            <span class="wp-legal-topnav-sep" aria-hidden="true">|</span>
            @foreach (config('legal.documents', []) as $key => $document)
                <a href="{{ route($document['route']) }}"
                   class="wp-legal-topnav-link {{ $key === $doc ? 'is-active' : '' }}">
                    {{ __($document['label_key']) }}
                </a>
            @endforeach
            <span class="wp-legal-topnav-sep" aria-hidden="true">|</span>
            <a href="{{ route('contact.index') }}" class="wp-legal-topnav-link">{{ __('common.nav.contact') }}</a>
        </nav>
        <div class="wp-legal-topbar-lang">
            @include('partials.wp-lang-switch', ['variant' => 'inline'])
        </div>
    </header>

    <article class="wp-legal">
        <p class="wp-legal-jurisdiction">{{ __('legal.applicable_law_notice') }}</p>

        <header class="wp-legal-head">
            <h1 class="wp-legal-title">{{ $title }}</h1>
            <p class="wp-legal-meta">
                {{ __('legal.last_updated', ['date' => $updatedAt]) }}
            </p>
        </header>

        <div class="wp-legal-body">
            @includeFirst([
                "legal.content.{$locale}.{$doc}",
                "legal.content.en.{$doc}",
            ])
        </div>
    </article>

    <footer class="wp-legal-footer">
        <p>&copy; {{ date('Y') }} WinProx</p>
        <nav class="wp-legal-footer-nav" aria-label="{{ __('legal.nav_label') }}">
            @foreach (config('legal.documents', []) as $key => $document)
                <a href="{{ route($document['route']) }}">{{ __($document['label_key']) }}</a>
                @if (! $loop->last)
                    <span aria-hidden="true">&middot;</span>
                @endif
            @endforeach
            <span aria-hidden="true">&middot;</span>
            <a href="{{ route('contact.index') }}">{{ __('common.nav.contact') }}</a>
        </nav>
    </footer>
</body>
</html>
