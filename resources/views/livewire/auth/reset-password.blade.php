<div class="wp-stack">
    <div class="wp-stack">
        <h1 class="wp-section-title">{{ __('auth.reset.title') }}</h1>
        <p class="wp-muted">{{ __('auth.reset.subtitle') }}</p>
    </div>

    <form wire:submit="resetPassword" class="wp-stack" x-data="{ show: false }">
        <div class="wp-field">
            <label class="wp-label" for="email">{{ __('auth.email') }}</label>
            <input type="email" id="email" class="wp-input" wire:model="email" autocomplete="email">
            @error('email') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <label class="wp-label" for="password">{{ __('auth.reset.password') }}</label>
            <div class="wp-input-group">
                <input type="password" :type="show ? 'text' : 'password'" id="password" class="wp-input" wire:model="password" autocomplete="new-password">
                <button type="button" class="wp-input-reveal" @click="show = !show"
                        :aria-label="show ? '{{ __('auth.hide_password') }}' : '{{ __('auth.show_password') }}'">
                    <x-wp-icon name="eye" class="wp-icon" x-show="!show" />
                    <x-wp-icon name="eye-slash" class="wp-icon" x-show="show" x-cloak />
                </button>
            </div>
            @error('password') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <label class="wp-label" for="password_confirmation">{{ __('auth.register.password_confirm') }}</label>
            <input type="password" id="password_confirmation" class="wp-input" wire:model="password_confirmation" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn--primary btn--block">{{ __('auth.reset.submit') }}</button>
        <a href="{{ route('login') }}" class="btn btn--ghost btn--block">{{ __('auth.forgot.back_to_login') }}</a>
    </form>
</div>
