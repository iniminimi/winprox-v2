<div class="wp-stack-tight"
     x-data="wpPortalPasskey({
         urls: {
             assertOptions: @js(route('public.time-portal.passkey.assert-options', $passkeyTenantId)),
             assert: @js(route('public.time-portal.passkey.assert', $passkeyTenantId)),
         },
         i18n: {
             error_cancelled: @js(__('portal.passkey.error_cancelled')),
             error_failed: @js(__('portal.passkey.error_failed')),
             error_device: @js(__('portal.passkey.error_device')),
         },
     })"
     x-show="supported" x-cloak>
    <button type="button" class="btn btn--primary btn--block" @click="verify" :disabled="busy">
        {{ __('portal.passkey.sign_in') }}
    </button>
    <p class="wp-error" x-show="error !== ''" x-text="error"></p>
    <p class="wp-hint">{{ $fallbackHint ?? __('portal.passkey.or_fallback') }}</p>
</div>
