@php
    $key = 'features.'.$slug;
@endphp
<div class="wp-welcome-section wp-welcome-section--alt wp-welcome-faq-page">
    <div class="wp-welcome-main wp-welcome-section-inner--wide wp-stack">
        <div class="wp-welcome-section--center wp-welcome-section-inner">
            <span class="wp-welcome-eyebrow">{{ __("{$key}.eyebrow") }}</span>
            <h1 class="wp-welcome-h2">{{ __("{$key}.title") }}</h1>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __("{$key}.lead") }}</p>
        </div>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.problem.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.problem.body") }}</p>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.solution.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.solution.body") }}</p>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.capabilities.title") }}</h2>
            <ul class="wp-welcome-checklist">
                @foreach (__("{$key}.capabilities.items") as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </article>

        <article class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-welcome-h3">{{ __("{$key}.audience.title") }}</h2>
            <p class="wp-text-body">{{ __("{$key}.audience.body") }}</p>
        </article>

        @include('partials.wp-marketing-related', [
            'links' => $relatedLinks,
            'title' => __('features.shared.related_title'),
        ])
    </div>
</div>
