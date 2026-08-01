<div
    @class([
        'wp-contact-view',
        auth()->check() ? 'wp-stack' : 'wp-public-page wp-stack',
    ])
    x-data="{
        ready: false,
        init() {
            const video = this.$refs.assistant;
            const markReady = () => { this.ready = true; };
            window.setTimeout(markReady, 4000);
            if (! video) {
                markReady();
                return;
            }
            if (video.readyState >= 3) {
                markReady();
                return;
            }
            video.addEventListener('canplaythrough', markReady, { once: true });
            video.addEventListener('error', markReady, { once: true });
            video.load();
        },
    }"
    x-bind:class="{ 'wp-contact-view--ready': ready }"
    x-cloak
>
    <div class="wp-contact-view__content">
        <x-wp-page-head-title
            icon="contact"
            :title="__('contact.title')"
            help-page="contact"
            :subtitle="__('contact.subtitle')"
        />

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

        <div class="wp-contact-assistant">
            <video
                x-ref="assistant"
                class="wp-contact-assistant__video"
                src="{{ asset('video/assistant.mp4') }}"
                width="140"
                height="140"
                autoplay
                loop
                muted
                playsinline
                preload="auto"
                aria-hidden="true"
            ></video>
        </div>
    </div>
</div>
