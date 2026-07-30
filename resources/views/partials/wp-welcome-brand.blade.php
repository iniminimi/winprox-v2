<a href="{{ route('welcome') }}" class="wp-welcome-brand" aria-label="{{ __('welcome.back_home') }}">
    <span class="wp-welcome-brand-logo wp-welcome-brand-logo--sm">
        <video
            class="wp-welcome-brand-logo__media"
            src="{{ asset('video/assistant_small.mp4') }}"
            width="80"
            height="80"
            muted
            loop
            playsinline
            autoplay
            preload="auto"
            aria-hidden="true"
        ></video>
    </span>
</a>
