<div class="wp-card wp-card-pad wp-stack wp-card--passkey"
     x-data="wpPortalPasskey({
         urls: {
             registerOptions: @js(route('public.time-portal.passkey.register-options', $passkeyTenantId)),
             register: @js(route('public.time-portal.passkey.register', $passkeyTenantId)),
         },
         i18n: {
             error_cancelled: @js(__('portal.passkey.error_cancelled')),
             error_failed: @js(__('portal.passkey.error_failed')),
         },
     })"
     x-show="supported" x-cloak>
    <div class="wp-passkey-badge" :class="{ 'is-busy': busy }" aria-hidden="true">
        <x-wp-icon name="fingerprint" />
    </div>
    <h2 class="wp-section-title">{{ __('portal.passkey.enroll_title') }}</h2>
    <p class="wp-muted">{{ __('portal.passkey.enroll_hint') }}</p>
    <p class="wp-error" x-show="error !== ''" x-text="error"></p>
    <div class="wp-stack-tight">
        <button type="button" class="btn btn--primary btn--block wp-passkey-btn" @click="enroll" :disabled="busy">
            <x-wp-icon name="fingerprint" />
            <span>{{ __('portal.passkey.enroll_cta') }}</span>
        </button>
        <button type="button" class="btn btn--ghost btn--block btn--sm" wire:click="dismissPasskeyOffer" :disabled="busy">
            {{ __('portal.passkey.enroll_skip') }}
        </button>
    </div>
</div>
