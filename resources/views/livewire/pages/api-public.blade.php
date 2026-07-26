<div class="wp-welcome-section wp-welcome-section--alt wp-welcome-faq-page">
    <div class="wp-welcome-main wp-welcome-section-inner--wide wp-stack">
        <div class="wp-welcome-section--center wp-welcome-section-inner">
            <span class="wp-welcome-eyebrow">{{ __('api_public.eyebrow') }}</span>
            <h1 class="wp-welcome-h2">{{ __('api_public.title') }}</h1>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('api_public.lead') }}</p>
        </div>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('api_public.overview.title') }}</h2>
            <p class="wp-text-body">{{ __('api_public.overview.body') }}</p>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('api_public.auth.title') }}</h2>
            <p class="wp-text-body">{{ __('api_public.auth.body') }}</p>
            <ul class="wp-welcome-checklist">
                @foreach (__('api_public.auth.items') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('api_public.endpoints.title') }}</h2>
            <div class="wp-stack">
                @foreach (__('api_public.endpoints.items') as $endpoint)
                    <div class="wp-stack-tight">
                        <p class="wp-subhead">{{ $endpoint['name'] }}</p>
                        <p class="wp-muted">{{ $endpoint['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('api_public.webhooks.title') }}</h2>
            <p class="wp-text-body">{{ __('api_public.webhooks.body') }}</p>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('api_public.access.title') }}</h2>
            <p class="wp-text-body">{{ __('api_public.access.body') }}</p>
        </article>

        @include('partials.wp-marketing-related', [
            'links' => $relatedLinks,
            'title' => __('api_public.related_title'),
        ])
    </div>
</div>
