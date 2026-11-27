@php
    $passkeyI18n = [
        'failed' => __('auth.phone.errors.failed'),
        'expired' => __('auth.phone.errors.expired'),
        'disabled' => __('auth.phone.errors.disabled'),
        'cancelled' => __('auth.phone.errors.cancelled'),
    ];
@endphp
<div class="wp-stack">
    <h1 class="wp-auth-title">{{ __('auth.phone.title') }}</h1>

    @unless ($ready)
        <p class="wp-text-body">{{ __('auth.phone.errors.expired') }}</p>
    @else
        <p class="wp-text-body"><strong>{{ $organization }}</strong></p>
        <p class="wp-text-body">{{ $computer }}</p>
        <p class="wp-text-body">{{ __('auth.phone.code') }} <strong>{{ $code }}</strong></p>

        @if ($status === \App\Models\BackofficeLoginChallenge::STATUS_APPROVED || $status === \App\Models\BackofficeLoginChallenge::STATUS_CONSUMED)
            <p class="wp-text-body" role="status">{{ __('auth.phone.done') }}</p>
        @elseif ($status === \App\Models\BackofficeLoginChallenge::STATUS_AUTHENTICATED)
            <div
                class="wp-stack"
                x-data="wpBackofficePasskey({
                    i18n: @js($passkeyI18n),
                    urls: {
                        registerOptions: @js(route('login.phone.register-options', ['token' => $token])),
                        register: @js(route('login.phone.register', ['token' => $token])),
                    }
                })"
            >
                <p class="wp-muted">{{ __('auth.phone.confirm_hint') }}</p>
                @if ($canRegister && ! $hasPasskey)
                    <button type="button" class="btn btn--ghost btn--block" @click="register()">{{ __('auth.phone.register') }}</button>
                    <p class="wp-error" x-show="error" x-text="error"></p>
                @endif
                <button type="button" class="btn btn--primary btn--block" wire:click="confirm">{{ __('auth.phone.confirm') }}</button>
            </div>
        @else
            <div
                class="wp-stack"
                x-data="wpBackofficePasskey({
                    i18n: @js($passkeyI18n),
                    urls: {
                        assertOptions: @js(route('login.phone.assert-options', ['token' => $token])),
                        assert: @js(route('login.phone.assert', ['token' => $token])),
                    }
                })"
            >
                @if ($hasPasskey)
                    <button type="button" class="btn btn--primary btn--block" @click="assert()">{{ __('auth.phone.biometric') }}</button>
                    <p class="wp-error" x-show="error" x-text="error"></p>
                @endif

                <form wire:submit="submitPassword" class="wp-stack">
                    <input type="password" class="wp-input" wire:model="password" autocomplete="current-password" placeholder="{{ __('auth.password') }}" aria-label="{{ __('auth.password') }}">
                    @error('password') <p class="wp-error">{{ $message }}</p> @enderror
                    <button type="submit" class="btn btn--primary btn--block">{{ __('auth.phone.submit_password') }}</button>
                </form>
            </div>
        @endif
    @endunless
</div>
