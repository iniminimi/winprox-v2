<div class="wp-stack">
    <div class="wp-stack">
        <h1 class="wp-section-title">{{ __('auth.title') }}</h1>
        <p class="wp-muted">{{ __('auth.subtitle') }}</p>
    </div>

    @if (session('status'))
        <div class="wp-pill wp-pill--done">{{ session('status') }}</div>
    @endif

    <form wire:submit="login" class="wp-stack" x-data="{ show: false }">
        <div class="wp-field">
            <label class="wp-label" for="email">{{ __('auth.email') }}</label>
            <input type="email" id="email" class="wp-input" wire:model="email" autocomplete="email" autofocus>
            @error('email') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-field">
            <label class="wp-label" for="password">{{ __('auth.password') }}</label>
            <div class="wp-input-group">
                <input :type="show ? 'text' : 'password'" id="password" class="wp-input" wire:model="password" autocomplete="current-password">
                <button type="button" class="wp-input-reveal" @click="show = !show"
                        :aria-label="show ? '{{ __('auth.hide_password') }}' : '{{ __('auth.show_password') }}'">
                    <x-wp-icon name="eye" class="wp-icon" x-show="!show" />
                    <x-wp-icon name="eye-slash" class="wp-icon" x-show="show" x-cloak />
                </button>
            </div>
            @error('password') <p class="wp-error">{{ $message }}</p> @enderror
        </div>

        <div class="wp-row">
            <label class="wp-check">
                <input type="checkbox" wire:model="remember">
                {{ __('auth.remember') }}
            </label>
            <a href="{{ route('password.request') }}">{{ __('auth.forgot_link') }}</a>
        </div>

        <button type="submit" class="btn btn--primary btn--block">{{ __('auth.submit') }}</button>
        <a href="{{ route('register') }}" class="btn btn--ghost btn--block">{{ __('auth.register_cta') }}</a>
    </form>
</div>
