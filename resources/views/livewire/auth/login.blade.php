<div class="wp-auth-login">
    @if (session('status'))
        <div class="wp-auth-notice wp-auth-notice--success" role="status">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="wp-auth-notice wp-auth-notice--error" role="alert">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="wp-auth-notice wp-auth-notice--error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form wire:submit="login" class="wp-auth-form wp-stack" x-data="{ show: false }">
        <input
            type="email"
            id="email"
            class="wp-input"
            wire:model="email"
            placeholder="{{ __('auth.email') }}"
            aria-label="{{ __('auth.email') }}"
            autocomplete="email"
            autofocus
        >
        @error('email')
            <p class="wp-error">{{ $message }}</p>
        @enderror

        <div class="wp-input-group">
            <input
                :type="show ? 'text' : 'password'"
                id="password"
                class="wp-input"
                wire:model="password"
                placeholder="{{ __('auth.password') }}"
                aria-label="{{ __('auth.password') }}"
                autocomplete="current-password"
            >
            <button
                type="button"
                class="wp-input-reveal"
                @click="show = !show"
                :aria-label="show ? '{{ __('auth.hide_password') }}' : '{{ __('auth.show_password') }}'"
            >
                <x-wp-icon name="eye" class="wp-icon" x-show="!show" />
                <x-wp-icon name="eye-slash" class="wp-icon" x-show="show" x-cloak />
            </button>
        </div>
        @error('password')
            <p class="wp-error">{{ $message }}</p>
        @enderror

        <div class="wp-auth-forgot">
            <a href="{{ route('password.request') }}">{{ __('auth.forgot_link') }}</a>
        </div>

        <button type="submit" class="btn btn--primary btn--block" wire:loading.attr="disabled">
            <x-wp-spinner wire:loading class="wp-mr-2" />
            <span wire:loading.remove>{{ __('auth.submit') }}</span>
            <span wire:loading>{{ __('auth.loading') }}</span>
        </button>
    </form>

    <div class="wp-auth-secondary">
        <a href="{{ route('register') }}" class="btn btn--ghost btn--block">{{ __('auth.register_cta') }}</a>
    </div>
</div>
