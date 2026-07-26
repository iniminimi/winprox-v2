<section id="faq" class="wp-welcome-section wp-welcome-section--alt" aria-labelledby="welcome-faq-title">
    <div class="wp-welcome-main wp-welcome-section-inner--wide">
        <div class="wp-welcome-section--center wp-welcome-section-inner">
            <span class="wp-welcome-eyebrow">{{ __('welcome.faq.eyebrow') }}</span>
            <x-wp-text-reveal
                as="h2"
                id="welcome-faq-title"
                class="wp-welcome-h2"
                :text="__('welcome.faq.title')"
            />
            <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('welcome.faq.lead') }}</p>
        </div>

        <div class="wp-welcome-faq-list">
            @foreach ($faqItems as $item)
                <article class="wp-welcome-faq-item wp-card wp-card-pad wp-stack" id="faq-{{ $item['slug'] }}">
                    <h3 class="wp-welcome-h3">{{ $item['title'] }}</h3>
                    @include('partials.wp-faq-item-body', ['item' => $item])
                </article>
            @endforeach
        </div>
    </div>
</section>
