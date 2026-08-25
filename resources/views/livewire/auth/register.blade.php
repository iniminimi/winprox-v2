@php
    $termsUrl = route('legal.terms');
    $privacyUrl = route('legal.privacy');
@endphp

<div
    class="wp-stack"
    x-data="{
        playingRegisterVideo: false,
        redirectTo: null,
        startRegisterVideo(redirectUrl) {
            this.redirectTo = redirectUrl;
            this.playingRegisterVideo = true;

            this.$nextTick(() => {
                const video = this.$refs.registerCompleteVideo;
                if (! video) {
                    this.finishRegisterVideo();
                    return;
                }

                video.currentTime = 0;
                video.play().catch(() => {});
            });
        },
        finishRegisterVideo() {
            if (! this.redirectTo) {
                return;
            }

            window.location.assign(this.redirectTo);
        },
    }"
    x-on:register-finished.window="startRegisterVideo($event.detail.redirectTo)"
>
    <template x-if="! playingRegisterVideo">
        <div class="wp-stack">
            <div class="wp-stack">
                <h1 class="wp-section-title">{{ __('auth.register.title') }}</h1>
                <p class="wp-muted">{{ __('auth.register.subtitle') }}</p>
            </div>

            <form wire:submit="register" class="wp-auth-form wp-stack">
        <h2 class="wp-auth-section-title">{{ __('auth.register.section_company') }}</h2>

        <input type="text" id="organization" class="wp-input" wire:model="organization"
               placeholder="{{ __('auth.register.placeholder_company') }}" autocomplete="organization" autofocus>
        @error('organization') <p class="wp-error">{{ $message }}</p> @enderror

        <input type="tel" id="phone" class="wp-input" wire:model="phone"
               placeholder="{{ __('auth.register.placeholder_phone') }}" inputmode="tel" autocomplete="tel">
        @error('phone') <p class="wp-error">{{ $message }}</p> @enderror

        <input type="text" id="street" class="wp-input" wire:model="street"
               placeholder="{{ __('auth.register.placeholder_street') }}" autocomplete="street-address">
        @error('street') <p class="wp-error">{{ $message }}</p> @enderror

        <input type="text" id="house_number" class="wp-input" wire:model="house_number"
               placeholder="{{ __('auth.register.placeholder_house_number') }}">
        @error('house_number') <p class="wp-error">{{ $message }}</p> @enderror

        <input type="text" id="postal_code" class="wp-input" wire:model="postal_code"
               placeholder="{{ __('auth.register.placeholder_postal_code') }}" autocomplete="postal-code">
        @error('postal_code') <p class="wp-error">{{ $message }}</p> @enderror

        <input type="text" id="city" class="wp-input" wire:model="city"
               placeholder="{{ __('auth.register.placeholder_city') }}" autocomplete="address-level2">
        @error('city') <p class="wp-error">{{ $message }}</p> @enderror

        <select id="country_code" class="wp-select" wire:model="country_code" autocomplete="country">
            <option value="">{{ __('auth.register.placeholder_country') }}</option>
            @foreach ($countries as $country)
                <option value="{{ $country['code'] }}">{{ $country['label'] }}</option>
            @endforeach
        </select>
        @error('country_code') <p class="wp-error">{{ $message }}</p> @enderror

        <h2 class="wp-auth-section-title">{{ __('auth.register.section_admin') }}</h2>

        <input type="text" id="name" class="wp-input" wire:model="name"
               placeholder="{{ __('auth.register.placeholder_name') }}" autocomplete="name">
        @error('name') <p class="wp-error">{{ $message }}</p> @enderror

        <input type="email" id="email" class="wp-input" wire:model="email"
               placeholder="{{ __('auth.email') }}" autocomplete="email">
        @error('email') <p class="wp-error">{{ $message }}</p> @enderror

        <x-wp-password-input wireModel="password" id="password" :placeholder="__('auth.password')" autocomplete="new-password" />
        @error('password') <p class="wp-error">{{ $message }}</p> @enderror

        <x-wp-password-input wireModel="password_confirmation" id="password_confirmation"
                             :placeholder="__('auth.register.password_confirm')" autocomplete="new-password" />
        @error('password_confirmation') <p class="wp-error">{{ $message }}</p> @enderror

        <label class="wp-check wp-check--boxed">
            <input type="checkbox" wire:model="accept_terms">
            <span>{!! __('auth.register.accept_terms_html', [
                'terms' => '<a href="'.e($termsUrl).'" class="wp-auth-inline-link" target="_blank" rel="noopener noreferrer">'.e(__('legal.documents.terms')).'</a>',
                'privacy' => '<a href="'.e($privacyUrl).'" class="wp-auth-inline-link" target="_blank" rel="noopener noreferrer">'.e(__('legal.documents.privacy')).'</a>',
            ]) !!}</span>
        </label>
        @error('accept_terms') <p class="wp-error">{{ $message }}</p> @enderror

                <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled" wire:target="register">
                    <x-wp-spinner wire:loading wire:target="register" class="wp-mr-2" />
                    <span wire:loading.remove wire:target="register">{{ __('auth.register.submit') }}</span>
                    <span wire:loading wire:target="register">{{ __('auth.register.submit_loading') }}</span>
                </button>
                <p class="wp-muted wp-text-sm" wire:loading.delay.longest wire:target="register">{{ __('auth.register.checking_email') }}</p>
                <a href="{{ route('login') }}" class="btn btn--ghost btn--block">{{ __('auth.register.have_account') }}</a>
            </form>
        </div>
    </template>

    <template x-if="playingRegisterVideo">
        <div class="wp-register-complete wp-stack">
            <video
                x-ref="registerCompleteVideo"
                class="wp-register-complete__video"
                src="{{ asset('video/assistant_task_160.mp4') }}"
                width="160"
                height="160"
                autoplay
                muted
                playsinline
                preload="auto"
                x-on:ended="finishRegisterVideo()"
                x-on:error="finishRegisterVideo()"
            ></video>
            <p class="wp-text-body"><strong>{{ __('dashboard.register_success.title') }}</strong></p>
            <p class="wp-muted">{{ __('dashboard.register_success.body') }}</p>
        </div>
    </template>
</div>
