@php
    $passkeyI18n = [
        'failed' => __('auth.phone.errors.failed'),
        'expired' => __('auth.phone.errors.expired'),
        'disabled' => __('auth.phone.errors.disabled'),
        'cancelled' => __('auth.phone.errors.cancelled'),
        'throttle' => __('auth.phone.errors.throttle'),
        'need_password' => __('auth.phone.need_password'),
    ];
@endphp
<div class="wp-stack">
    <h1 class="wp-auth-title">{{ __('auth.phone.title') }}</h1>

    @unless ($ready)
        <p class="wp-text-body">{{ __('auth.phone.errors.expired') }}</p>
    @else
        @if ($organization !== '')
            <p class="wp-text-body"><strong>{{ $organization }}</strong></p>
        @endif
        <p class="wp-text-body">{{ $computer }}</p>

        @if ($status === \App\Models\BackofficeLoginChallenge::STATUS_APPROVED || $status === \App\Models\BackofficeLoginChallenge::STATUS_CONSUMED)
            <p class="wp-text-body" role="status">{{ __('auth.phone.done') }}</p>
        @elseif ($status === \App\Models\BackofficeLoginChallenge::STATUS_AUTHENTICATED)
            <div
                class="wp-stack"
                x-data="wpBackofficePasskey({
                    auto: @js($canRegister && ! $hasPasskey),
                    i18n: @js($passkeyI18n),
                    urls: {
                        registerOptions: @js(route('login.phone.register-options', ['token' => $token])),
                        register: @js(route('login.phone.register', ['token' => $token])),
                    }
                })"
            >
                <p class="wp-muted">{{ __('auth.phone.confirm_hint') }}</p>
                @if ($canRegister && ! $hasPasskey)
                    <div class="wp-passkey-badge" :class="{ 'is-busy': busy }" aria-hidden="true">
                        <x-wp-icon name="fingerprint" />
                    </div>
                    <button type="button" class="btn btn--primary btn--block wp-passkey-btn" @click="register()" :disabled="busy">
                        <x-wp-icon name="fingerprint" />
                        <span>{{ __('auth.phone.biometric') }}</span>
                    </button>
                    <p class="wp-error" x-show="error" x-text="error"></p>
                @endif
                <button type="button" class="btn btn--ghost btn--block" wire:click="confirm">{{ __('auth.phone.confirm') }}</button>
            </div>
        @else
            <div
                class="wp-stack"
                x-data="wpBackofficePasskey({
                    i18n: @js($passkeyI18n),
                    urls: {
                        password: @js(route('login.phone.password', ['token' => $token])),
                        assertOptions: @js(route('login.phone.assert-options', ['token' => $token])),
                        assert: @js(route('login.phone.assert', ['token' => $token])),
                        registerOptions: @js(route('login.phone.register-options', ['token' => $token])),
                        register: @js(route('login.phone.register', ['token' => $token])),
                    }
                })"
            >
                <div class="wp-passkey-badge" :class="{ 'is-busy': busy }" aria-hidden="true">
                    <x-wp-icon name="fingerprint" />
                </div>
                <p class="wp-muted" x-show="!hasKey">{{ __('auth.phone.first_time') }}</p>
                <form class="wp-stack" x-show="!hasKey" @submit.prevent="begin()">
                    <input type="email" class="wp-input" autocomplete="username" placeholder="{{ __('auth.email') }}" aria-label="{{ __('auth.email') }}">
                    <input type="password" class="wp-input" autocomplete="current-password" placeholder="{{ __('auth.password') }}" aria-label="{{ __('auth.password') }}">
                </form>
                <button type="button" class="btn btn--primary btn--block wp-passkey-btn" @click="begin()" :disabled="busy">
                    <x-wp-icon name="fingerprint" />
                    <span>{{ __('auth.phone.biometric') }}</span>
                </button>
                <p class="wp-error" x-show="error" x-text="error"></p>
            </div>
        @endif
    @endunless
</div>
