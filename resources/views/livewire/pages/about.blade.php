<div class="wp-welcome-section wp-welcome-section--alt wp-welcome-faq-page">
    <div class="wp-welcome-main wp-welcome-section-inner--wide wp-stack">
        <div class="wp-welcome-section--center wp-welcome-section-inner">
            <span class="wp-welcome-eyebrow">{{ __('about.eyebrow') }}</span>
            <h1 class="wp-welcome-h2">{{ __('about.title') }}</h1>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('about.lead') }}</p>
        </div>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('about.what.title') }}</h2>
            <p class="wp-text-body">{{ __('about.what.body') }}</p>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('about.audience.title') }}</h2>
            <ul class="wp-welcome-checklist">
                @foreach (__('about.audience.items') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('about.sectors.title') }}</h2>
            <ul class="wp-welcome-checklist">
                @foreach (__('about.sectors.items') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('about.modules.title') }}</h2>
            <div class="wp-stack">
                @foreach (__('about.modules.items') as $module)
                    <div class="wp-stack-tight">
                        <p class="wp-subhead">{{ $module['name'] }}</p>
                        <p class="wp-muted">{{ $module['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('about.tech.title') }}</h2>
            <p class="wp-text-body">{{ __('about.tech.body') }}</p>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('about.compliance.title') }}</h2>
            <ul class="wp-welcome-checklist">
                @foreach (__('about.compliance.items') as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __('about.languages.title') }}</h2>
            <p class="wp-text-body">{{ __('about.languages.body') }}</p>
        </article>

        @include('partials.wp-marketing-related', [
            'links' => $relatedLinks,
            'title' => __('about.related_title'),
        ])
    </div>
</div>
