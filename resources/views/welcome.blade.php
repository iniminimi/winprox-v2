@php
    $locale = app()->getLocale();
    $welcomeVideoRel = 'video/welcome.mp4';
    $welcomeVideoAvailable = is_file(public_path($welcomeVideoRel));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" translate="no" data-theme="standard">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('welcome.meta_title') }}</title>
    @include('partials.social-meta', [
        'title' => __('welcome.social.og_title'),
        'description' => __('welcome.social.og_description'),
        'url' => route('welcome'),
    ])
    @include('partials.wp-json-ld', [
        'graphs' => [
            \App\Support\Marketing\JsonLd::organization(),
            \App\Support\Marketing\JsonLd::softwareApplication(),
        ],
    ])
    @include('partials.favicon')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="wp-shell wp-welcome-shell"
      @if (! empty($promoEngageToken)) data-promo-engage-url="{{ route('promo.track.engage') }}" @endif>
    <div class="wp-welcome-top">
        @include('partials.wp-welcome-nav')

        <header class="wp-welcome-hero wp-welcome-hero--minimal">
            <div class="wp-welcome-main">
                <figure class="wp-welcome-hero-minimal__photo-frame">
                    <img
                        class="wp-welcome-hero-minimal__photo"
                        src="{{ asset('images/welcome/welcome_reception.jpg') }}"
                        alt=""
                        decoding="async"
                        fetchpriority="high"
                    >
                    <div class="wp-welcome-hero-minimal">
                        <h1 class="wp-welcome-hero-minimal__brand">{{ __('welcome.hero.brand') }}</h1>
                        <p class="wp-welcome-hero-minimal__headline">{{ __('welcome.hero.headline') }}</p>
                        <p class="wp-welcome-hero-minimal__subtitle">{{ __('welcome.hero.subtitle') }}</p>
                        <ol class="wp-welcome-hero-flow" aria-label="{{ __('welcome.hero.flow') }}">
                            @foreach ([
                                ['icon' => 'qr', 'key' => 'scan'],
                                ['icon' => 'alert-triangle', 'key' => 'report'],
                                ['icon' => 'clipboard-check', 'key' => 'work'],
                                ['icon' => 'check', 'key' => 'done'],
                            ] as $step)
                                <li class="wp-welcome-hero-flow__step">
                                    <span class="wp-welcome-hero-flow__icon" aria-hidden="true">
                                        <x-wp-icon :name="$step['icon']" />
                                    </span>
                                    <span class="wp-welcome-hero-flow__label">{{ __('welcome.hero.flow_steps.'.$step['key']) }}</span>
                                </li>
                            @endforeach
                        </ol>
                        <div class="wp-welcome-cta-row">
                            <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('welcome.hero.cta_start') }}</a>
                            <a href="#video" class="btn btn--ghost btn--lg">{{ __('welcome.hero.cta_how') }}</a>
                        </div>
                        <a href="{{ route('work-on-location') }}" class="wp-welcome-feature-board" aria-label="{{ __('welcome.hero.feature_board.title') }}">
                            <span class="wp-pill wp-pill--new">{{ __('welcome.hero.feature_board.badge') }}</span>
                            <p class="wp-welcome-feature-board__title">{{ __('welcome.hero.feature_board.title') }}</p>
                            <p class="wp-welcome-feature-board__text">{{ __('welcome.hero.feature_board.text') }}</p>
                        </a>
                    </div>
                </figure>
            </div>
        </header>
    </div>

    <section id="video" class="wp-welcome-video-section" aria-label="{{ __('welcome.video.title') }}">
        <div class="wp-welcome-main">
            @if ($welcomeVideoAvailable)
                @include('partials.wp-video-player', [
                    'src' => asset($welcomeVideoRel),
                    'title' => __('welcome.video.title'),
                ])
            @else
                <div class="wp-welcome-media-placeholder wp-welcome-media-placeholder--video" role="img" aria-label="{{ __('welcome.video.placeholder') }}">
                    <p>{{ __('welcome.video.placeholder') }}</p>
                </div>
            @endif
        </div>
    </section>

    <footer class="wp-welcome-footer">
        <div class="wp-welcome-footer-inner">
            @include('partials.wp-welcome-brand', ['showAssistant' => true])
            <nav class="wp-welcome-footer-nav" aria-label="{{ __('legal.index_title') }}">
                @foreach (config('product_docs.documents', []) as $docMeta)
                    <a href="{{ route($docMeta['route']) }}">{{ __($docMeta['label_key']) }}</a>
                @endforeach
                @foreach (config('legal.documents', []) as $docMeta)
                    <a href="{{ route($docMeta['route']) }}" target="_blank" rel="noopener">{{ __($docMeta['label_key']) }}</a>
                @endforeach
                <a href="{{ route('contact.index') }}">{{ __('welcome.footer.contact') }}</a>
            </nav>
            <p class="wp-welcome-footer-copy">
                <a href="{{ route('welcome.classic') }}" class="wp-welcome-footer-egg" title="est. 1995">
                    <img
                        src="{{ asset('images/welcome/1995/easter_egg.gif') }}"
                        alt=""
                        width="32"
                        height="32"
                        loading="lazy"
                        decoding="async"
                    >
                </a>
                <span>&copy; {{ date('Y') }} WinProx. {{ __('welcome.footer.rights') }}</span>
            </p>
        </div>
    </footer>
</body>
</html>
