@php
    $locale = app()->getLocale();
    $welcomeVideoRel = 'video/welcome.mp4';
    $welcomeVideoAvailable = is_file(public_path($welcomeVideoRel));
    $welcomeJsonLdGraphs = [
        \App\Support\Marketing\JsonLd::organization(),
        \App\Support\Marketing\JsonLd::softwareApplication(),
    ];
    foreach (\App\Support\Marketing\SitemapVideo::forRoute('welcome', $locale) as $welcomeVideoEntry) {
        $welcomeJsonLdGraphs[] = \App\Support\Marketing\JsonLd::videoObject($welcomeVideoEntry);
    }
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
        'graphs' => $welcomeJsonLdGraphs,
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
                        <p class="wp-welcome-hero-minimal__brand">{{ __('welcome.hero.brand') }}</p>
                        <h1 class="wp-welcome-hero-minimal__headline">{{ __('welcome.hero.headline') }}</h1>
                        <p class="wp-welcome-hero-minimal__subtitle">{{ __('welcome.hero.subtitle') }}</p>
                        <ol class="wp-welcome-hero-flow" aria-label="{{ __('welcome.hero.flow') }}">
                            @foreach ([
                                ['icon' => 'qr', 'key' => 'scan'],
                                ['icon' => 'clock', 'key' => 'punch'],
                                ['icon' => 'roster', 'key' => 'hours'],
                                ['icon' => 'check', 'key' => 'secretariat'],
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
                            <a href="{{ route('demo') }}" class="btn btn--primary btn--lg">{{ __('welcome.hero.cta_demo') }}</a>
                            <a href="#video" class="btn btn--ghost btn--lg">{{ __('welcome.hero.cta_how') }}</a>
                        </div>
                        <p class="wp-text-body">
                            {{ __('welcome.hero.trial') }}
                            <a href="{{ \App\Support\Marketing\MarketingDomain::authUrl('/register') }}">{{ __('welcome.hero.cta_self') }}</a>
                        </p>
                        @unless (\App\Support\Marketing\MarketingDomain::routeHidden('checkmate'))
                        <a href="{{ route('checkmate') }}" class="wp-welcome-feature-board" aria-label="{{ __('welcome.hero.feature_board.title') }}">
                            <span class="wp-pill wp-pill--new">{{ __('welcome.hero.feature_board.badge') }}</span>
                            <p class="wp-welcome-feature-board__title">{{ __('welcome.hero.feature_board.title') }}</p>
                            <p class="wp-welcome-feature-board__text">{{ __('welcome.hero.feature_board.text') }}</p>
                            <p class="wp-welcome-feature-board__price">{{ __('welcome.hero.feature_board.price') }}</p>
                        </a>
                        @endunless
                    </div>
                </figure>
            </div>
        </header>
    </div>

    <section class="wp-welcome-clock-section" aria-label="{{ __('welcome.clock.title') }}">
        <div class="wp-welcome-main">
            <div class="wp-welcome-split wp-welcome-split--qr">
                <article class="wp-card wp-card-pad wp-stack">
                    <figure class="wp-welcome-feature-hero-logo wp-welcome-feature-hero-logo--wide">
                        <img
                            src="{{ asset('images/welcome/winprox_time_module_logo.jpg') }}"
                            alt="{{ __('features.time.logo_alt') }}"
                            class="wp-welcome-feature-hero-logo-img"
                            decoding="async"
                        >
                    </figure>
                    <h2 class="wp-welcome-h3">{{ __('welcome.clock.title') }}</h2>
                    <p class="wp-text-body">{{ __('welcome.clock.lead') }}</p>
                    <ul class="wp-welcome-checklist">
                        @foreach (__('welcome.clock.items') as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                    <p class="wp-text-body">{{ __('welcome.clock.close') }}</p>
                    <p class="wp-text-body">{{ __('welcome.clock.price_note', ['price' => config('marketing.clock_rent_monthly_eur')]) }}</p>
                    <div class="wp-welcome-cta-row">
                        <a href="{{ route('features.time') }}" class="btn btn--primary">{{ __('welcome.clock.cta_time') }}</a>
                        <a href="{{ route('prikklok') }}" class="btn btn--ghost">{{ __('welcome.clock.cta_clock') }}</a>
                        <a href="{{ route('demo') }}" class="btn btn--ghost">{{ __('welcome.clock.cta_demo') }}</a>
                    </div>
                </article>
                <figure class="wp-welcome-screenshot wp-welcome-screenshot--desktop wp-landing-visual wp-landing-visual--compact">
                    <img
                        src="{{ asset('images/landing/LCD.jpg') }}"
                        alt="{{ __('welcome.clock.image_alt') }}"
                        class="wp-welcome-screenshot__img"
                        loading="lazy"
                        decoding="async"
                    >
                </figure>
            </div>
        </div>
    </section>

    <section class="wp-welcome-section wp-welcome-section--alt wp-welcome-employees" aria-label="{{ __('welcome.employees.title') }}">
        <div class="wp-welcome-main">
            <div class="wp-welcome-split">
                <figure class="wp-landing-visual wp-landing-visual--compact wp-welcome-screenshot wp-welcome-screenshot--phone">
                    <img
                        src="{{ asset('images/welcome/screenshot_worker.jpg') }}"
                        alt="{{ __('welcome.employees.image_alt') }}"
                        class="wp-welcome-screenshot__img"
                        loading="lazy"
                        decoding="async"
                    >
                </figure>
                <article class="wp-card wp-card-pad wp-stack">
                    <h2 class="wp-welcome-h3">{{ __('welcome.employees.title') }}</h2>
                    <p class="wp-text-body">{{ __('welcome.employees.lead') }}</p>
                    <ul class="wp-welcome-checklist">
                        @foreach (__('welcome.employees.items') as $item)
                            <li><span><strong>{{ $item['label'] }}</strong> — {{ $item['text'] }}</span></li>
                        @endforeach
                    </ul>
                    <p class="wp-muted">{{ __('welcome.employees.note') }}</p>
                </article>
            </div>
        </div>
    </section>

    <section class="wp-welcome-section wp-welcome-backoffice" aria-label="{{ __('welcome.backoffice.title') }}">
        <div class="wp-welcome-main">
            <div class="wp-welcome-split">
                <article class="wp-card wp-card-pad wp-stack">
                    <h2 class="wp-welcome-h3">{{ __('welcome.backoffice.title') }}</h2>
                    <p class="wp-text-body">{{ __('welcome.backoffice.lead') }}</p>
                    <ul class="wp-welcome-checklist">
                        @foreach (__('welcome.backoffice.items') as $item)
                            <li><span><strong>{{ $item['label'] }}</strong> — {{ $item['text'] }}</span></li>
                        @endforeach
                    </ul>
                    <p class="wp-text-body">{{ __('welcome.backoffice.closing') }}</p>
                    <p class="wp-muted">{{ __('welcome.backoffice.note') }}</p>
                </article>
                <figure class="wp-landing-visual wp-welcome-screenshot wp-welcome-screenshot--desktop">
                    <img
                        src="{{ asset('images/welcome/screenshot_backoffice.jpg') }}"
                        alt="{{ __('welcome.backoffice.image_alt') }}"
                        class="wp-welcome-screenshot__img"
                        loading="lazy"
                        decoding="async"
                    >
                </figure>
            </div>
        </div>
    </section>

    <section class="wp-welcome-section wp-welcome-section--alt" aria-label="{{ __('welcome.guidance.title') }}">
        <div class="wp-welcome-main">
            <div class="wp-welcome-split">
                <article class="wp-card wp-card-pad wp-stack">
                    <span class="wp-welcome-eyebrow">{{ __('welcome.guidance.eyebrow') }}</span>
                    <h2 class="wp-welcome-h3">{{ __('welcome.guidance.title') }}</h2>
                    <p class="wp-text-body">{{ __('welcome.guidance.lead') }}</p>
                    <ul class="wp-welcome-checklist">
                        @foreach (__('welcome.guidance.items') as $item)
                            <li><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                    <p class="wp-muted">{{ __('welcome.guidance.note') }}</p>
                    <div class="wp-welcome-cta-row">
                        <a href="{{ route('demo') }}" class="btn btn--primary">{{ __('welcome.guidance.cta') }}</a>
                    </div>
                </article>
            </div>
        </div>
    </section>

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
