@php
    $locale = app()->getLocale();
    $promoVideos = collect(__('promo.video.items'))->filter(function (array $item) use ($locale): bool {
        $rel = "video/{$locale}/{$item['basename']}_{$locale}_01.mp4";

        return is_file(public_path($rel));
    });
@endphp
<x-layouts.public
    :title="__('promo.title')"
    :social-title="__('promo.social.og_title')"
    :social-description="__('promo.social.og_description')"
    :social-url="route('promo')"
    og-context="site"
>
    <div class="wp-promo-top">
        @include('partials.wp-lang-switch', ['variant' => 'inline'])
    </div>

    <div class="wp-stack wp-promo">
        <h1 class="wp-page-title">{{ __('promo.title') }}</h1>
        <p class="wp-text-body">{{ __('promo.tagline') }}</p>

        <ul class="wp-list wp-list--bullets">
            <li>{{ __('promo.bullet_1') }}</li>
            <li>{{ __('promo.bullet_2') }}</li>
            <li>{{ __('promo.bullet_3') }}</li>
        </ul>

        @if ($promoVideos->isNotEmpty())
            <section class="wp-promo-video" aria-labelledby="promo-video-title">
                <p class="wp-promo-video-eyebrow">{{ __('promo.video.eyebrow') }}</p>
                <h2 id="promo-video-title" class="wp-section-title">{{ __('promo.video.title') }}</h2>
                <p class="wp-promo-video-lead wp-muted">{{ __('promo.video.lead') }}</p>

                <div class="wp-promo-video-grid">
                    @foreach ($promoVideos as $item)
                        <article class="wp-promo-video-card wp-card">
                            <div class="wp-card-pad">
                                <h3 class="wp-promo-video-card-title">{{ $item['title'] }}</h3>
                                <p class="wp-muted">{{ $item['description'] }}</p>
                            </div>
                            <div class="wp-promo-video-card-media">
                                @include('partials.wp-locale-video', [
                                    'basename' => $item['basename'],
                                    'title' => $item['title'],
                                ])
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <a href="https://winprox.app" class="btn btn--primary">
            {{ __('promo.cta') }}
        </a>
    </div>
</x-layouts.public>
