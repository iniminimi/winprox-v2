<div class="wp-welcome-section wp-welcome-section--alt wp-welcome-faq-page">
    <div class="wp-welcome-main wp-welcome-section-inner--wide">
        <div class="wp-welcome-section--center wp-welcome-section-inner">
            <span class="wp-welcome-eyebrow">{{ __('faq.eyebrow') }}</span>
            <h1 class="wp-welcome-h2" id="faq-page-title">{{ __('faq.title') }}</h1>
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('faq.subtitle') }}</p>
        </div>

        <div class="wp-welcome-faq-list">
            @foreach ($items as $item)
                <article class="wp-welcome-faq-item wp-card wp-card-pad wp-stack" id="faq-{{ $item['slug'] }}">
                    <h2 class="wp-welcome-h3">{{ $item['title'] }}</h2>
                    @include('partials.wp-faq-item-body', ['item' => $item])
                </article>
            @endforeach
        </div>
    </div>
</div>
