<x-layouts.public
    :title="__('promo.title')"
    :social-title="__('promo.social.og_title')"
    :social-description="__('promo.social.og_description')"
    :social-url="route('promo')"
>
    <div class="wp-stack wp-promo">
        <h1 class="wp-page-title">{{ __('promo.title') }}</h1>
        <p class="wp-text-body">{{ __('promo.tagline') }}</p>

        <ul class="wp-list wp-list--bullets">
            <li>{{ __('promo.bullet_1') }}</li>
            <li>{{ __('promo.bullet_2') }}</li>
            <li>{{ __('promo.bullet_3') }}</li>
        </ul>

        <a href="https://winprox.app" class="btn btn--primary">
            {{ __('promo.cta') }}
        </a>
    </div>
</x-layouts.public>
