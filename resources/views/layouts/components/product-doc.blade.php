<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" translate="no" data-theme="{{ $uiTheme ?? 'simple' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — WinProx</title>
    @include('partials.social-meta', ['title' => $title . ' — WinProx'])
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell wp-product-doc">
    <header class="wp-legal-topbar">
        <nav class="wp-legal-topnav" aria-label="{{ __('product_docs.chrome.nav_label') }}">
            <a href="{{ route('welcome') }}" class="wp-legal-topnav-link">{{ __('product_docs.chrome.back_home') }}</a>
            <span class="wp-legal-topnav-sep" aria-hidden="true">|</span>
            @foreach (config('product_docs.documents', []) as $key => $document)
                <a href="{{ route($document['route']) }}"
                   class="wp-legal-topnav-link {{ $key === $doc ? 'is-active' : '' }}">
                    {{ __($document['label_key']) }}
                </a>
            @endforeach
            <span class="wp-legal-topnav-sep" aria-hidden="true">|</span>
            <a href="{{ route('faq.public') }}" class="wp-legal-topnav-link">{{ __('welcome.nav.faq') }}</a>
            <a href="{{ route('contact.index') }}" class="wp-legal-topnav-link">{{ __('common.nav.contact') }}</a>
        </nav>
        <div class="wp-legal-topbar-lang">
            @include('partials.wp-lang-switch', ['variant' => 'inline'])
        </div>
    </header>

    <article class="wp-product-doc-sheet">
        <p class="wp-product-doc-print-hint no-print">{{ __('product_docs.chrome.print_hint') }}</p>

        <header class="wp-product-doc-head">
            <div>
                <h1 class="wp-product-doc-title">{{ $title }}</h1>
                @if (! empty($content['tagline']))
                    <p class="wp-product-doc-tagline">{{ $content['tagline'] }}</p>
                @endif
            </div>
            <div class="wp-product-doc-meta">
                winprox.app<br>
                {{ __('product_docs.chrome.last_updated', ['date' => $updatedAt]) }}
            </div>
        </header>

        @if (! empty($content['highlight']))
            <p class="wp-product-doc-highlight">{{ $content['highlight'] }}</p>
        @endif

        @if (! empty($content['intro']))
            <p class="wp-product-doc-intro">{{ $content['intro'] }}</p>
        @endif

        @if (! empty($content['flow']) && is_array($content['flow']))
            <div class="wp-product-doc-flow" aria-hidden="true">
                @foreach ($content['flow'] as $step)
                    @if (! $loop->first)
                        <span class="wp-product-doc-flow__arrow">→</span>
                    @endif
                    <span>{{ $step }}</span>
                @endforeach
            </div>
        @endif

        @if (! empty($content['source']))
            <p class="wp-product-doc-source">{{ $content['source'] }}</p>
        @endif

        <div class="wp-product-doc-columns">
            <div class="wp-product-doc-col">
                @foreach (($content['left'] ?? []) as $card)
                    @include('product-docs.partials.card', ['card' => $card])
                @endforeach
            </div>
            <div class="wp-product-doc-col">
                @foreach (($content['right'] ?? []) as $card)
                    @include('product-docs.partials.card', ['card' => $card])
                @endforeach
            </div>
        </div>

        @if (! empty($content['full']) && is_array($content['full']))
            <div class="wp-product-doc-full">
                @foreach ($content['full'] as $card)
                    @include('product-docs.partials.card', ['card' => $card])
                @endforeach
            </div>
        @endif

        <footer class="wp-product-doc-footer">
            <span>{{ $content['footer_left'] ?? '' }}</span>
            <span>{{ $content['footer_right'] ?? '' }}</span>
        </footer>
    </article>
</body>
</html>
