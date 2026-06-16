@php
    $locale = app()->getLocale();

    $promoVideosFor = function (array $items) use ($locale) {
        return collect($items)->filter(function (array $item) use ($locale): bool {
            $suffix = $item['suffix'] ?? '_01';
            $rel = "video/{$locale}/{$item['basename']}_{$locale}{$suffix}.mp4";

            return is_file(public_path($rel));
        });
    };

    $qrPortalVideos = $promoVideosFor(__('promo.video.qr_portal.items'));
    $beheerportaalVideos = $promoVideosFor(__('promo.video.beheerportaal.items'));
    $hasPromoVideos = $qrPortalVideos->isNotEmpty() || $beheerportaalVideos->isNotEmpty();
@endphp
<x-layouts.public
    :title="__('promo.title')"
    :social-title="__('promo.social.og_title')"
    :social-description="__('promo.social.og_description')"
    :social-url="route('promo')"
    :portal-bg-url="asset('images/promo/background.jpg')"
    body-class="wp-public-body wp-promo-body"
    og-context="site"
>
    <div class="wp-stack wp-promo">
        <div class="wp-promo-top">
            @include('partials.wp-lang-switch', ['variant' => 'inline'])
        </div>

        <div class="wp-promo-panel-dark">
            <h1 class="wp-promo-panel-title">{{ __('promo.title') }}</h1>
            <p class="wp-promo-panel-lead">{{ __('promo.tagline') }}</p>

            <ul class="wp-promo-checklist">
                <li>{{ __('promo.bullet_1') }}</li>
                <li>{{ __('promo.bullet_2') }}</li>
                <li>{{ __('promo.bullet_3') }}</li>
            </ul>

            <div class="wp-promo-panel-glow" aria-hidden="true"></div>
        </div>

        @if ($hasPromoVideos)
            <div class="wp-promo-panel-glass">
                <div class="wp-stack">
                    <section class="wp-promo-video" aria-labelledby="promo-video-title">
                        <p class="wp-promo-video-eyebrow">{{ __('promo.video.eyebrow') }}</p>
                        <h2 id="promo-video-title" class="wp-section-title">{{ __('promo.video.title') }}</h2>
                        <p class="wp-promo-video-lead wp-muted">{{ __('promo.video.lead') }}</p>
                    </section>

                    @if ($qrPortalVideos->isNotEmpty())
                        <section class="wp-promo-video" aria-labelledby="promo-qr-portal-title">
                            <h2 id="promo-qr-portal-title" class="wp-section-title">
                                {{ __('promo.video.qr_portal.title') }}
                            </h2>

                            <div class="wp-promo-video-grid">
                                @foreach ($qrPortalVideos as $item)
                                    <article class="wp-promo-video-card wp-card">
                                        <div class="wp-card-pad">
                                            <h3 class="wp-promo-video-card-title">{{ $item['title'] }}</h3>
                                            <p class="wp-muted">{{ $item['description'] }}</p>
                                        </div>
                                        <div class="wp-promo-video-card-media">
                                            @include('partials.wp-locale-video', [
                                                'basename' => $item['basename'],
                                                'suffix' => $item['suffix'] ?? '_01',
                                                'title' => $item['title'],
                                            ])
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if ($beheerportaalVideos->isNotEmpty())
                        <section class="wp-promo-video" aria-labelledby="promo-beheerportaal-title">
                            <h2 id="promo-beheerportaal-title" class="wp-section-title">
                                {{ __('promo.video.beheerportaal.title') }}
                            </h2>

                            <div class="wp-promo-video-grid">
                                @foreach ($beheerportaalVideos as $item)
                                    <article class="wp-promo-video-card wp-card">
                                        <div class="wp-card-pad">
                                            <p class="wp-promo-video-card-title">{{ $item['description'] }}</p>
                                        </div>
                                        <div class="wp-promo-video-card-media">
                                            @include('partials.wp-locale-video', [
                                                'basename' => $item['basename'],
                                                'suffix' => $item['suffix'] ?? '_01',
                                                'title' => $item['description'],
                                            ])
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            </div>
        @endif

        <a href="https://winprox.app" class="btn btn--primary">
            {{ __('promo.cta') }}
        </a>
    </div>
</x-layouts.public>
