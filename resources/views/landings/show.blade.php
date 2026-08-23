<x-layouts.marketing
    :title="$layoutTitle"
    :social-title="$layoutSocialTitle"
    :social-description="$layoutSocialDescription"
    :json-ld-graphs="$layoutJsonLdGraphs"
    :promo-engage-page="$slug"
    :promo-tracking-token="$promoTrackingToken ?? null"
    :promo-has-video="$videoSrc !== null"
>
    @php
        $key = 'landings.'.$slug;
        $reserveVideo = $slug === 'industry' || $videoSrc !== null;
    @endphp
    <div class="wp-welcome-section wp-welcome-section--alt wp-welcome-faq-page">
        <div class="wp-welcome-main wp-welcome-section-inner--wide wp-stack">
            <div class="wp-welcome-section--center wp-welcome-section-inner">
                <span class="wp-welcome-eyebrow">{{ __("{$key}.eyebrow") }}</span>
                <h1 class="wp-welcome-h2">{{ __("{$key}.title") }}</h1>
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __("{$key}.lead") }}</p>
                @if (\Illuminate\Support\Facades\Lang::has($key.'.flow'))
                    <p class="wp-text-body">{{ __("{$key}.flow") }}</p>
                @endif
                <div class="wp-cluster wp-welcome-section--center">
                    <a href="{{ route('register') }}" class="btn btn--primary btn--lg">{{ __('landings.shared.cta_register') }}</a>
                    <a href="{{ route('login') }}" class="btn btn--ghost btn--lg">{{ __('landings.shared.cta_login') }}</a>
                    @if ($videoSrc !== null)
                        <a href="#landing-video" class="btn btn--ghost btn--lg">{{ __('landings.shared.cta_video') }}</a>
                    @endif
                </div>
            </div>

            @if (! empty($promoRecipientLabel))
                <div class="wp-flash wp-flash--muted" role="status">
                    <p class="wp-text-body">{{ __('landings.shared.recipient_welcome', ['label' => $promoRecipientLabel]) }}</p>
                </div>
            @endif

            @if ($reserveVideo)
                <article id="landing-video" class="wp-card wp-card-pad wp-stack">
                    <h2 class="wp-welcome-h3">{{ \Illuminate\Support\Facades\Lang::has($key.'.video.title') ? __("{$key}.video.title") : __('landings.shared.video_title') }}</h2>
                    @if (\Illuminate\Support\Facades\Lang::has($key.'.video.lead'))
                        <p class="wp-text-body">{{ __("{$key}.video.lead") }}</p>
                    @endif
                    @if ($videoSrc !== null)
                        <div @if (! empty($promoTrackingToken)) data-promo-video-key="{{ $slug }}" @endif>
                            @include('partials.wp-video-player', [
                                'src' => $videoSrc,
                                'title' => \Illuminate\Support\Facades\Lang::has($key.'.video.title') ? __("{$key}.video.title") : __('landings.shared.video_title'),
                            ])
                        </div>
                    @else
                        <div
                            class="wp-welcome-media-placeholder wp-welcome-media-placeholder--video"
                            role="img"
                            aria-label="{{ __('landings.shared.video_placeholder') }}"
                        >
                            <p>{{ __('landings.shared.video_placeholder') }}</p>
                        </div>
                    @endif
                </article>
            @endif

            @if ($slug === 'industry')
                @include('landings.partials.industry', ['key' => $key])
            @else
                <article class="wp-card wp-card-pad wp-stack">
                    <h2 class="wp-welcome-h3">{{ __("{$key}.why.title") }}</h2>
                    <ul class="wp-welcome-checklist">
                        @foreach (__("{$key}.why.items") as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </article>
            @endif

            @include('partials.wp-marketing-related', [
                'links' => $relatedLinks,
                'title' => __('landings.shared.related_title'),
            ])
        </div>
    </div>
</x-layouts.marketing>
