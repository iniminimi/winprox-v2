@php
    $locale = app()->getLocale();
    $welcomeDesktopScreenshotRel = "images/welcome/screenshot_{$locale}_desktop.jpg";
    $welcomeDesktopScreenshotAvailable = is_file(public_path($welcomeDesktopScreenshotRel));
    $welcomeMobileScreenshotRel = "images/welcome/screenshot_{$locale}_gsm.jpg";
    $welcomeMobileScreenshotAvailable = is_file(public_path($welcomeMobileScreenshotRel));
    $welcomeVideoRel = "video/{$locale}/issue_{$locale}_01.mp4";
    $welcomeVideoAvailable = is_file(public_path($welcomeVideoRel));
    $welcomeEsgImageRel = 'images/welcome/ESG.jpg';
    $welcomeEsgImageAvailable = is_file(public_path($welcomeEsgImageRel));
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
    @include('partials.favicon')
    @vite(['resources/css/app.css'])
</head>
<body class="wp-shell wp-welcome-shell">
    <div class="wp-welcome-top">
        <nav class="wp-welcome-nav" aria-label="{{ __('welcome.meta_title') }}">
            <div class="wp-welcome-nav-inner">
                @include('partials.wp-welcome-brand')
                <div class="wp-welcome-nav-links">
                    <a href="#producten">{{ __('welcome.nav.products') }}</a>
                    <a href="#platform">{{ __('welcome.nav.platform') }}</a>
                    <a href="#esg">{{ __('welcome.nav.esg') }}</a>
                    <a href="#qr">{{ __('welcome.nav.qr') }}</a>
                    <a href="#organisaties">{{ __('welcome.nav.sectors') }}</a>
                    <a href="#video">{{ __('welcome.nav.video') }}</a>
                </div>
                <div class="wp-welcome-nav-actions">
                    @include('partials.wp-lang-switch', ['variant' => 'inline'])
                    <a href="{{ route('login') }}" class="btn btn--ghost btn--sm">{{ __('welcome.login') }}</a>
                    <a href="{{ route('register') }}" class="btn btn--primary btn--sm">{{ __('welcome.hero.cta_start') }}</a>
                </div>
            </div>
        </nav>

        <header class="wp-welcome-hero wp-welcome-hero--split">
            <div class="wp-welcome-main wp-welcome-hero-split">
                <div class="wp-welcome-hero-copy">
                    <span class="wp-welcome-badge">{{ __('welcome.hero.badge') }}</span>
                    <h1 class="wp-welcome-h1 wp-welcome-h1--hero">{{ __('welcome.hero.title') }}</h1>
                    <p class="wp-welcome-lead wp-welcome-lead--hero">{{ __('welcome.hero.subtitle') }}</p>
                    <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.hero.body') }}</p>
                    <div class="wp-welcome-cta-row wp-welcome-cta-row--start">
                        <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('welcome.hero.cta_start') }}</a>
                        <a href="#video" class="btn btn--ghost btn--lg">{{ __('welcome.hero.cta_video') }}</a>
                    </div>
                </div>
                <div class="wp-welcome-hero-visual">
                    @if ($welcomeDesktopScreenshotAvailable)
                        <figure class="wp-welcome-screenshot wp-welcome-screenshot--hero wp-welcome-screenshot--desktop">
                            <img
                                src="{{ asset($welcomeDesktopScreenshotRel) }}"
                                alt="{{ __('welcome.hero.desktop_screenshot_alt') }}"
                                class="wp-welcome-screenshot__img"
                                width="1180"
                                height="925"
                                loading="eager"
                                decoding="async"
                            >
                        </figure>
                    @else
                        <div class="wp-welcome-media-placeholder wp-welcome-media-placeholder--desktop" role="img" aria-label="{{ __('welcome.hero.desktop_screenshot_alt') }}">
                            <p>{{ __('welcome.hero.desktop_screenshot_alt') }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </header>
    </div>

    <main>
        <section id="producten" class="wp-welcome-section wp-welcome-section--alt wp-welcome-section--center" aria-labelledby="welcome-products-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <span class="wp-welcome-eyebrow">{{ __('welcome.products.eyebrow') }}</span>
                <h2 id="welcome-products-title" class="wp-welcome-h2">{{ __('welcome.products.title') }}</h2>
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.products.lead') }}</p>
                <div class="wp-welcome-product-grid">
                    @foreach (['facility', 'time'] as $productKey)
                        <article class="wp-welcome-product-card">
                            <figure class="wp-welcome-product-card__logo">
                                <img
                                    src="{{ asset('images/welcome/winprox_'.$productKey.'_logo.jpg') }}"
                                    alt="{{ __('welcome.products.'.$productKey.'.logo_alt') }}"
                                    class="wp-welcome-product-card__logo-img"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </figure>
                            <h3>{{ __('welcome.products.'.$productKey.'.name') }}</h3>
                            <p class="wp-welcome-product-card__tagline">{{ __('welcome.products.'.$productKey.'.tagline') }}</p>
                            <p class="wp-welcome-product-card__body">{{ __('welcome.products.'.$productKey.'.body') }}</p>
                            <ul class="wp-welcome-pillar-list">
                                @foreach (__('welcome.products.'.$productKey.'.bullets') as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="platform" class="wp-welcome-section wp-welcome-section--center" aria-labelledby="welcome-pillars-title">
            <div class="wp-welcome-section-inner--wide wp-welcome-main">
                <span class="wp-welcome-eyebrow">{{ __('welcome.pillars.eyebrow') }}</span>
                <h2 id="welcome-pillars-title" class="wp-welcome-h2">{{ __('welcome.pillars.title') }}</h2>
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.pillars.lead') }}</p>
                <div class="wp-welcome-pillar-grid">
                    @foreach (__('welcome.pillars.items') as $pillar)
                        <article @class([
                            'wp-welcome-pillar-card',
                            'wp-welcome-pillar-card--esg' => $pillar['icon'] === 'circle',
                        ])>
                            <span class="wp-welcome-pillar-icon" @if($pillar['icon'] === 'circle') aria-hidden="true" @endif>
                                <x-wp-icon :name="$pillar['icon']" class="wp-icon" />
                            </span>
                            <h3>{{ $pillar['title'] }}</h3>
                            <ul class="wp-welcome-pillar-list">
                                @foreach ($pillar['bullets'] as $bullet)
                                    <li>{{ $bullet }}</li>
                                @endforeach
                            </ul>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="esg" class="wp-welcome-section wp-welcome-section--alt wp-welcome-section--esg" aria-labelledby="welcome-esg-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-split">
                    <div>
                        <span class="wp-welcome-eyebrow wp-welcome-eyebrow--esg">{{ __('welcome.esg.eyebrow') }}</span>
                        <h2 id="welcome-esg-title" class="wp-welcome-h2">{{ __('welcome.esg.title') }}</h2>
                        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.esg.body') }}</p>
                        <div class="wp-welcome-esg-pillars">
                            @foreach (__('welcome.esg.pillars') as $esgPillar)
                                <div class="wp-welcome-esg-block">
                                    <h3>{{ $esgPillar['title'] }}</h3>
                                    <ul class="wp-welcome-checklist wp-welcome-checklist--esg">
                                        @foreach ($esgPillar['items'] as $item)
                                            <li>{{ $item }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="wp-welcome-esg-visual">
                        @if ($welcomeEsgImageAvailable)
                            <figure class="wp-welcome-esg-figure">
                                <img
                                    src="{{ asset($welcomeEsgImageRel) }}"
                                    alt="{{ __('welcome.esg.visual_alt') }}"
                                    class="wp-welcome-esg-figure__img"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </figure>
                        @else
                            <div class="wp-welcome-media-placeholder" role="img" aria-label="{{ __('welcome.esg.visual_alt') }}">
                                <p>{{ __('welcome.esg.visual_alt') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section id="qr" class="wp-welcome-section" aria-labelledby="welcome-qr-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-split wp-welcome-split--qr">
                    <div>
                        <span class="wp-welcome-eyebrow">{{ __('welcome.qr.eyebrow') }}</span>
                        <h2 id="welcome-qr-title" class="wp-welcome-h2">{{ __('welcome.qr.title') }}</h2>
                        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.qr.lead') }}</p>
                        <ul class="wp-welcome-checklist wp-welcome-checklist--spaced">
                            @foreach (__('welcome.qr.benefits') as $benefit)
                                <li>{{ $benefit }}</li>
                            @endforeach
                        </ul>
                        <p class="wp-welcome-badge-ok">{{ __('welcome.qr.footer') }}</p>
                    </div>
                    <div class="wp-welcome-qr-visual">
                        @if ($welcomeMobileScreenshotAvailable)
                            <figure class="wp-welcome-screenshot wp-welcome-screenshot--phone">
                                <img
                                    src="{{ asset($welcomeMobileScreenshotRel) }}"
                                    alt="{{ __('welcome.qr.screenshot_alt') }}"
                                    class="wp-welcome-screenshot__img"
                                    width="340"
                                    height="666"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </figure>
                        @else
                            <div class="wp-welcome-media-placeholder wp-welcome-media-placeholder--phone" role="img" aria-label="{{ __('welcome.qr.screenshot_alt') }}">
                                <p>{{ __('welcome.qr.screenshot_alt') }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </section>

        <section id="flow" class="wp-welcome-section wp-welcome-section--alt wp-welcome-section--center" aria-labelledby="welcome-flow-title">
            <div class="wp-welcome-section-inner--wide wp-welcome-main">
                <span class="wp-welcome-eyebrow">{{ __('welcome.flow.eyebrow') }}</span>
                <h2 id="welcome-flow-title" class="wp-welcome-h2">{{ __('welcome.flow.title') }}</h2>
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.flow.lead') }}</p>
                <ol class="wp-welcome-timeline">
                    @foreach (__('welcome.flow.steps') as $step)
                        <li class="wp-welcome-timeline__step">
                            <span class="wp-welcome-timeline__dot" aria-hidden="true"></span>
                            <span class="wp-welcome-timeline__label">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section id="organisaties" class="wp-welcome-section" aria-labelledby="welcome-sectors-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('welcome.sectors.eyebrow') }}</span>
                    <h2 id="welcome-sectors-title" class="wp-welcome-h2">{{ __('welcome.sectors.title') }}</h2>
                    <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.sectors.lead') }}</p>
                </div>
                <div class="wp-welcome-sector-grid">
                    @foreach (__('welcome.sectors.items') as $sector)
                        <article class="wp-welcome-sector-card">
                            <span class="wp-welcome-sector-icon"><x-wp-icon :name="$sector['icon']" class="wp-icon" /></span>
                            <h3>{{ $sector['name'] }}</h3>
                            <p>{{ $sector['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="video" class="wp-welcome-section wp-welcome-section--alt wp-welcome-section--center" aria-labelledby="welcome-trust-title">
            <div class="wp-welcome-section-inner--wide wp-welcome-main">
                <span class="wp-welcome-eyebrow">{{ __('welcome.trust.eyebrow') }}</span>
                <h2 id="welcome-trust-title" class="wp-welcome-h2">{{ __('welcome.trust.title') }}</h2>

                <div class="wp-welcome-trust-languages">
                    <h3 class="wp-welcome-h3">{{ __('welcome.trust.languages_title') }}</h3>
                    <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.trust.languages_body') }}</p>
                </div>

                <ul class="wp-welcome-trust-grid">
                    @foreach (__('welcome.trust.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <div class="wp-welcome-trust-media">
                    <div class="wp-welcome-trust-video">
                        <h3 class="wp-welcome-h3">{{ __('welcome.trust.video_title') }}</h3>
                        @if ($welcomeVideoAvailable)
                            @include('partials.wp-locale-video', [
                                'basename' => 'issue',
                                'title' => __('welcome.trust.video_title'),
                            ])
                        @else
                            <div class="wp-welcome-media-placeholder wp-welcome-media-placeholder--video" role="img" aria-label="{{ __('welcome.trust.video_placeholder') }}">
                                <p>{{ __('welcome.trust.video_placeholder') }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="wp-welcome-trust-compare">
                        <h3 class="wp-welcome-h3">{{ __('welcome.trust.comparison_title') }}</h3>
                        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.trust.comparison_body') }}</p>
                        <a href="{{ route('comparison') }}" class="btn btn--primary">{{ __('welcome.trust.comparison_link') }}</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-section--center wp-welcome-section--closing" aria-labelledby="welcome-closing-title">
            <div class="wp-welcome-section-inner">
                <h2 id="welcome-closing-title" class="wp-welcome-h1 wp-welcome-h1--closing">{{ __('welcome.closing.title') }}</h2>
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.closing.body') }}</p>
                <div class="wp-welcome-cta-row">
                    <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('welcome.closing.cta_start') }}</a>
                    <a href="{{ route('contact.index') }}" class="btn btn--ghost btn--lg">{{ __('welcome.closing.cta_contact') }}</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="wp-welcome-footer">
        <div class="wp-welcome-footer-inner">
            @include('partials.wp-welcome-brand')
            <nav class="wp-welcome-footer-nav" aria-label="{{ __('legal.index_title') }}">
                @foreach (config('legal.documents', []) as $docMeta)
                    <a href="{{ route($docMeta['route']) }}" target="_blank" rel="noopener">{{ __($docMeta['label_key']) }}</a>
                @endforeach
                <a href="{{ route('contact.index') }}">{{ __('welcome.footer.contact') }}</a>
            </nav>
            <p>&copy; {{ date('Y') }} WinProx. {{ __('welcome.footer.rights') }}</p>
        </div>
    </footer>
</body>
</html>
