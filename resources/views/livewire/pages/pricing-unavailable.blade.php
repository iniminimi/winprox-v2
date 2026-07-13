<section class="wp-welcome-section wp-welcome-section--center" aria-labelledby="pricing-unavailable-title">
    <div class="wp-welcome-section-inner">
        <span class="wp-welcome-eyebrow">{{ __('pricing.eyebrow') }}</span>
        <h1 id="pricing-unavailable-title" class="wp-welcome-h2">{{ __('pricing.title') }}</h1>
        <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __('pricing.message') }}</p>
        <div class="wp-welcome-cta-row">
            @auth
                <a href="{{ route('subscription.index') }}" class="btn btn--primary">{{ __('pricing.subscription_link') }}</a>
            @else
                <a href="{{ route('register') }}" class="btn btn--primary">{{ __('pricing.trial_link') }}</a>
                <a href="{{ route('welcome') }}" class="btn btn--ghost">{{ __('pricing.back') }}</a>
            @endauth
        </div>
    </div>
</section>
