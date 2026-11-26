<x-layouts.marketing
    :title="$layoutTitle"
    :social-title="$layoutSocialTitle"
    :social-description="$layoutSocialDescription"
    :json-ld-graphs="$layoutJsonLdGraphs"
>
    <div class="wp-welcome-section wp-welcome-section--alt wp-welcome-faq-page">
        <div class="wp-welcome-main wp-welcome-section-inner--wide wp-stack">
            <div class="wp-welcome-section--center wp-welcome-section-inner">
                <span class="wp-welcome-eyebrow">{{ __($page.'.eyebrow') }}</span>
                <h1 class="wp-welcome-h2">{{ __($page.'.title') }}</h1>
                <p class="wp-welcome-lead wp-welcome-lead--sm">{{ __($page.'.lead') }}</p>
                <p class="wp-text-body">{{ __($page.'.intro') }}</p>
            </div>

            <article class="wp-card wp-card-pad wp-stack">
                <h2 class="wp-welcome-h3">{{ __($page.'.section_title') }}</h2>
                <ul class="wp-welcome-checklist">
                    @foreach (__($page.'.points') as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </article>

            @if (\Illuminate\Support\Facades\Lang::has($page.'.privacy_title'))
                <article class="wp-card wp-card-pad wp-stack">
                    <h2 class="wp-welcome-h3">{{ __($page.'.privacy_title') }}</h2>
                    <p class="wp-text-body">{{ __($page.'.privacy_body') }}</p>
                </article>
            @endif

            <p class="wp-text-body">{{ __($page.'.aside_hint') }}</p>
            <div class="wp-welcome-cta-row">
                <a href="{{ route('prikklok') }}" class="btn btn--ghost">{{ __($page.'.back') }}</a>
                <a href="{{ route(__($page.'.aside_route')) }}" class="btn btn--ghost">{{ __($page.'.aside_link') }}</a>
            </div>
        </div>
    </div>
</x-layouts.marketing>
