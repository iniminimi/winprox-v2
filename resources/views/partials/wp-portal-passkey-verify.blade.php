{{-- Vereist x-data="wpPortalPasskey(...)" op de omliggende kaart (badge + divider leven in dezelfde scope). --}}
<button type="button" class="btn btn--primary btn--block wp-passkey-btn" @click="verify" :disabled="busy" x-show="supported" x-cloak>
    <x-wp-icon name="fingerprint" />
    <span>{{ __('portal.passkey.sign_in') }}</span>
</button>
<p class="wp-error" x-show="error !== ''" x-text="error"></p>
