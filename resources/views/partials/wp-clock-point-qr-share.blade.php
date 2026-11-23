{{-- Aanmeldlink + mail, gedeeld door Time → Clock Points en het Checkmate-dashboard. --}}
@php
    $clockPointSignInUrl = $clockPoint->emailPortalUrl();
    $clockPointWhatsAppUrl = 'https://wa.me/?text='.rawurlencode($clockPointSignInUrl);
@endphp
<div class="wp-modal-section">
    <div class="wp-stack-tight">
        <h3 class="wp-label">{{ __('time.clock_points.qr.email.heading') }}</h3>
        <p class="wp-muted">{{ __('time.clock_points.qr.email.hint') }}</p>
    </div>
    <div class="wp-field" x-data="{ copied: false }">
        <label class="wp-filter-inline-label" for="{{ $linkInputId }}">{{ __('time.clock_points.qr.email.link_label') }}</label>
        <div class="wp-cluster wp-cluster--wrap">
            <input
                id="{{ $linkInputId }}"
                type="text"
                class="wp-input"
                value="{{ $clockPointSignInUrl }}"
                readonly
                aria-label="{{ __('time.clock_points.qr.email.link_label') }}"
                x-ref="link"
                @focus="$el.select()"
            >
            <button
                type="button"
                class="btn btn--ghost"
                @click="
                    const linkInput = $refs.link;
                    linkInput.select();
                    linkInput.setSelectionRange(0, linkInput.value.length);
                    if (window.isSecureContext && navigator.clipboard) {
                        navigator.clipboard.writeText(linkInput.value).then(() => copied = true).catch(() => {});
                    } else {
                        copied = document.execCommand('copy');
                    }
                    setTimeout(() => copied = false, 1500);
                "
                x-text="copied ? @js(__('time.clock_points.qr.email.link_copied')) : @js(__('time.clock_points.qr.email.link_copy'))"
            ></button>
            <x-wp-tooltip class="wp-tooltip--below" :text="__('time.clock_points.qr.email.link_whatsapp_hint')">
                <a
                    href="{{ $clockPointWhatsAppUrl }}"
                    class="btn btn--ghost"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="{{ __('time.clock_points.qr.email.link_whatsapp') }}"
                >
                    <x-wp-icon name="whatsapp" class="wp-icon wp-icon--whatsapp" />
                    <span>{{ __('time.clock_points.qr.email.link_whatsapp') }}</span>
                </a>
            </x-wp-tooltip>
        </div>
        <p class="wp-muted wp-text-sm">{{ __('time.clock_points.qr.email.link_hint') }}</p>
    </div>
    @if (filled($flash ?? null))
        <div class="wp-flash wp-flash--success">{{ $flash }}</div>
    @endif
    <div class="wp-field">
        <div class="wp-cluster wp-cluster--wrap">
            <input
                id="{{ $emailInputId }}"
                type="email"
                class="wp-input"
                wire:model="{{ $emailModel }}"
                autocomplete="email"
                aria-label="{{ __('time.clock_points.qr.email.label') }}"
                placeholder="{{ __('time.clock_points.qr.email.label') }}"
            >
            <button
                type="button"
                class="btn btn--primary"
                wire:click="{{ $sendMethod }}"
                wire:loading.attr="disabled"
            >
                {{ __('time.clock_points.qr.email.send') }}
            </button>
        </div>
        @error($errorKey) <p class="wp-error">{{ $message }}</p> @enderror
    </div>
</div>
