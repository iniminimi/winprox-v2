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
    $welcomeIotImageRel = 'images/welcome/IoT.jpg';
    $welcomeIotImageAvailable = is_file(public_path($welcomeIotImageRel));
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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="wp-shell wp-welcome-shell">
    <div class="wp-welcome-top">
        @include('partials.wp-welcome-nav')

        <header class="wp-welcome-hero wp-welcome-hero--center">
            <div class="wp-welcome-main wp-welcome-hero-stack">
                <div class="wp-welcome-hero-copy wp-welcome-hero-copy--center">
                    <span class="wp-welcome-badge">{{ __('welcome.hero.badge') }}</span>
                    <x-wp-text-reveal
                        as="h1"
                        class="wp-welcome-h1 wp-welcome-h1--hero"
                        :lines="__('welcome.hero.title_lines')"
                        :accent="__('welcome.hero.title_accent')"
                    />
                    <p class="wp-welcome-lead wp-welcome-lead--hero">{{ __('welcome.hero.subtitle') }}</p>
                    <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.hero.body') }}</p>
                    <div class="wp-welcome-cta-row">
                        <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('welcome.hero.cta_start') }}</a>
                        <a href="#video" class="btn btn--ghost btn--lg">{{ __('welcome.hero.cta_video') }}</a>
                    </div>
                    <p class="wp-welcome-hero-note">{{ __('welcome.hero.hero_note') }}</p>
                </div>
                <div class="wp-welcome-hero-visual wp-welcome-hero-visual--below">
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

    <div class="wp-welcome-trust-bar" aria-label="{{ __('welcome.trust.eyebrow') }}">
        <div class="wp-welcome-main">
            <ul class="wp-welcome-trust-bar__list">
                @foreach (__('welcome.trust_bar.items') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </div>

    <main>
        <section id="flow" class="wp-welcome-section wp-welcome-section--alt wp-welcome-section--center" aria-labelledby="welcome-flow-title">
            <div class="wp-welcome-section-inner--wide wp-welcome-main">
                <span class="wp-welcome-eyebrow">{{ __('welcome.flow.eyebrow') }}</span>
                <x-wp-text-reveal
                    as="h2"
                    id="welcome-flow-title"
                    class="wp-welcome-h2"
                    :text="__('welcome.flow.title')"
                />
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.flow.lead') }}</p>
                <div class="wp-welcome-steps-card">
                    @foreach (__('welcome.flow.steps') as $index => $step)
                        <article class="wp-welcome-step-card">
                            <span class="wp-welcome-step-num" aria-hidden="true">{{ $index + 1 }}</span>
                            <p class="wp-welcome-flow-step-label">{{ $step['label'] }}</p>
                            <h3>{{ $step['title'] }}</h3>
                        </article>
                    @endforeach
                </div>
                <p class="wp-welcome-flow-foot">{{ __('welcome.flow.footer') }}</p>
            </div>
        </section>

        <section id="qr" class="wp-welcome-section" aria-labelledby="welcome-qr-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-split wp-welcome-split--qr">
                    <div>
                        <span class="wp-welcome-eyebrow">{{ __('welcome.qr.eyebrow') }}</span>
                        <x-wp-text-reveal
                            as="h2"
                            id="welcome-qr-title"
                            class="wp-welcome-h2"
                            :text="__('welcome.qr.title')"
                        />
                        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.qr.lead') }}</p>
                        <ul class="wp-welcome-unit-features">
                            @foreach (__('welcome.qr.unit_features') as $feature)
                                <li>{{ $feature }}</li>
                            @endforeach
                        </ul>
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

        <section id="producten" class="wp-welcome-section wp-welcome-section--alt wp-welcome-section--center" aria-labelledby="welcome-products-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <span class="wp-welcome-eyebrow">{{ __('welcome.products.eyebrow') }}</span>
                <x-wp-text-reveal
                    as="h2"
                    id="welcome-products-title"
                    class="wp-welcome-h2"
                    :text="__('welcome.products.title')"
                />
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.products.lead') }}</p>
                <div class="wp-welcome-product-grid">
                    @foreach ([
                        'facility' => ['route' => 'features.facility', 'logo' => 'facility'],
                        'time' => ['route' => 'features.time', 'logo' => 'time'],
                    ] as $productKey => $productMeta)
                        <article class="wp-welcome-product-card">
                            <figure class="wp-welcome-product-card__logo">
                                <img
                                    src="{{ asset('images/welcome/winprox_'.$productMeta['logo'].'_logo.jpg') }}"
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
                            <p class="wp-welcome-product-card__more">
                                <a href="{{ route($productMeta['route']) }}" class="btn btn--ghost btn--sm">{{ __('welcome.products.learn_more') }}</a>
                            </p>
                        </article>
                    @endforeach
                </div>
                <p class="wp-welcome-product-card__more">
                    {{ __('welcome.products.iot_note') }}
                    <a href="{{ route('features.iot') }}" class="btn btn--ghost btn--sm">{{ __('welcome.products.iot_cta') }}</a>
                </p>
            </div>
        </section>

        <section id="platform" class="wp-welcome-section wp-welcome-section--center" aria-labelledby="welcome-pillars-title">
            <div class="wp-welcome-section-inner--wide wp-welcome-main">
                <span class="wp-welcome-eyebrow">{{ __('welcome.pillars.eyebrow') }}</span>
                <x-wp-text-reveal
                    as="h2"
                    id="welcome-pillars-title"
                    class="wp-welcome-h2"
                    :text="__('welcome.pillars.title')"
                />
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
                        <x-wp-text-reveal
                            as="h2"
                            id="welcome-esg-title"
                            class="wp-welcome-h2"
                            :text="__('welcome.esg.title')"
                        />
                        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.esg.body') }}</p>
                        <p class="wp-welcome-product-card__more">
                            <a href="{{ route('features.esg') }}" class="btn btn--ghost btn--sm">{{ __('welcome.products.learn_more') }}</a>
                        </p>
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

        <section id="iot" class="wp-welcome-section wp-welcome-iot" aria-labelledby="welcome-iot-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-split wp-welcome-split--iot">
                    <div class="wp-welcome-iot-visual">
                        @if ($welcomeIotImageAvailable)
                            <figure class="wp-welcome-iot-figure">
                                <img
                                    src="{{ asset($welcomeIotImageRel) }}"
                                    alt="{{ __('welcome.iot.visual_alt') }}"
                                    class="wp-welcome-iot-figure__img"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </figure>
                        @else
                            <div class="wp-welcome-media-placeholder" role="img" aria-label="{{ __('welcome.iot.visual_alt') }}">
                                <p>{{ __('welcome.iot.visual_alt') }}</p>
                            </div>
                        @endif
                    </div>
                    <div>
                        <span class="wp-welcome-eyebrow">{{ __('welcome.iot.eyebrow') }}</span>
                        <x-wp-text-reveal
                            as="h2"
                            id="welcome-iot-title"
                            class="wp-welcome-h2"
                            :text="__('welcome.iot.title')"
                        />
                        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.iot.body') }}</p>
                        <ul class="wp-welcome-checklist wp-welcome-iot__points">
                            @foreach (__('welcome.iot.items') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <p class="wp-welcome-product-card__more">
                            <a href="{{ route('features.iot') }}" class="btn btn--ghost btn--sm">{{ __('welcome.iot.cta') }}</a>
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section id="organisaties" class="wp-welcome-section wp-welcome-section--alt" aria-labelledby="welcome-sectors-title">
            <div class="wp-welcome-main wp-welcome-section-inner--wide">
                <div class="wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('welcome.sectors.eyebrow') }}</span>
                    <x-wp-text-reveal
                        as="h2"
                        id="welcome-sectors-title"
                        class="wp-welcome-h2"
                        :text="__('welcome.sectors.title')"
                    />
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

        <section id="video" class="wp-welcome-section wp-welcome-section--trust" aria-labelledby="welcome-trust-title">
            <div class="wp-welcome-section-inner--wide wp-welcome-main">
                <div class="wp-welcome-trust-head wp-welcome-section--center wp-welcome-section-inner">
                    <span class="wp-welcome-eyebrow">{{ __('welcome.trust.eyebrow') }}</span>
                    <x-wp-text-reveal
                        as="h2"
                        id="welcome-trust-title"
                        class="wp-welcome-h2"
                        :text="__('welcome.trust.title')"
                    />
                </div>

                <div class="wp-welcome-trust-stage">
                    <div class="wp-welcome-trust-phone">
                        <p class="wp-welcome-trust-bento__label">{{ __('welcome.trust.video_title') }}</p>
                        @if ($welcomeVideoAvailable)
                            @include('partials.wp-locale-video', [
                                'basename' => 'issue',
                                'title' => __('welcome.trust.video_title'),
                            ])
                        @elseif ($welcomeDesktopScreenshotAvailable)
                            <figure class="wp-welcome-trust-bento__screenshot">
                                <img
                                    src="{{ asset($welcomeDesktopScreenshotRel) }}"
                                    alt="{{ __('welcome.hero.desktop_screenshot_alt') }}"
                                    class="wp-welcome-screenshot__img"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </figure>
                        @else
                            <div class="wp-welcome-media-placeholder wp-welcome-media-placeholder--video" role="img" aria-label="{{ __('welcome.trust.video_placeholder') }}">
                                <p>{{ __('welcome.trust.video_placeholder') }}</p>
                            </div>
                        @endif
                    </div>

                    <aside class="wp-welcome-trust-lang wp-card">
                        <h3 class="wp-welcome-h3">{{ __('welcome.trust.languages_title') }}</h3>
                        <p class="wp-welcome-trust-lang__body">{{ __('welcome.trust.languages_body') }}</p>
                        <ul class="wp-welcome-trust-lang__chips" aria-label="{{ __('welcome.trust.languages_title') }}">
                            @foreach (__('welcome.trust.locale_chips') as $chip)
                                <li><span class="wp-welcome-trust-lang__chip notranslate" translate="no">{{ $chip }}</span></li>
                            @endforeach
                        </ul>
                    </aside>
                </div>

                <ul class="wp-welcome-trust-chips">
                    @foreach (__('welcome.trust.items') as $item)
                        <li><span class="wp-welcome-trust-chip">{{ $item }}</span></li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="wp-welcome-section wp-welcome-section--closing wp-welcome-closing-panel" aria-labelledby="welcome-closing-title">
            <div class="wp-welcome-section-inner wp-welcome-closing-panel__inner">
                <x-wp-text-reveal
                    as="h2"
                    id="welcome-closing-title"
                    class="wp-welcome-h1 wp-welcome-h1--closing"
                    :lines="__('welcome.closing.title_lines')"
                />
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.closing.body') }}</p>
                <div class="wp-welcome-cta-row">
                    <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('welcome.closing.cta_start') }}</a>
                    <a href="{{ route('contact.index') }}" class="btn btn--ghost btn--lg wp-welcome-closing-panel__ghost">{{ __('welcome.closing.cta_contact') }}</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="wp-welcome-footer">
        <div class="wp-welcome-footer-inner">
            @include('partials.wp-welcome-brand')
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
