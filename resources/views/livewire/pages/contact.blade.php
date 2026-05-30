<div class="{{ auth()->check() ? 'wp-stack' : 'wp-public-page wp-stack' }}">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('contact.title') }}</h1>
        <p class="wp-muted">{{ __('contact.subtitle') }}</p>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <p>{{ __('contact.intro') }}</p>
        <p>
            <a href="mailto:{{ __('contact.email') }}" class="btn btn--primary">{{ __('contact.email_cta') }}</a>
        </p>
        <p class="wp-muted">{{ __('contact.assistant_hint') }}</p>

        @auth
            <p>
                <a href="{{ route('dashboard') }}" class="btn btn--ghost btn--sm">{{ __('contact.back_dashboard') }}</a>
            </p>
        @endauth
    </div>
</div>
