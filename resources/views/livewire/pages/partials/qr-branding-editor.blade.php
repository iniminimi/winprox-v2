@php
    $averySetting = $organisationTenant->qrStickerSheetSetting(\App\Support\Qr\QrStickerSheetTemplate::Avery62x89R);
    $printableSetting = $organisationTenant->qrStickerSheetSetting(\App\Support\Qr\QrStickerSheetTemplate::printablePageSettings());
    $hasCustomBackground = filled($averySetting?->background_path) || filled($printableSetting?->background_path);
    $previewTargets = 'qrBrandingHeaderText, qrBrandingBackgroundPreset, qrBrandingTenantLogo, qrBrandingTenantAddress, qrBrandingBackground, saveQrBrandingSettings, removeQrBrandingBackground';
@endphp

<div class="wp-settings-split wp-qr-sticker-editor">
    <div class="wp-qr-sticker-editor-controls wp-stack-tight">
        <form
            wire:submit="saveQrBrandingSettings"
            class="wp-stack-tight"
            x-data="{ openText: false, openBackground: false, openLogo: false }"
        >
            <div class="wp-disclosure-block">
                <button
                    type="button"
                    class="wp-disclosure-block-toggle wp-team-row-toggle"
                    @click="openText = !openText"
                    :aria-expanded="openText"
                >
                    <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': openText }" />
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.branding.section_text') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openText" x-cloak>
                    <div class="wp-field">
                        <label class="wp-label" for="qrBrandingHeaderText">{{ __('settings.qr_stickers.branding.header_label') }}</label>
                        <input
                            type="text"
                            id="qrBrandingHeaderText"
                            class="wp-input"
                            wire:model.live.debounce.500ms="qrBrandingHeaderText"
                            maxlength="{{ \App\Support\Qr\Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS }}"
                            placeholder="{{ __('settings.qr_stickers.branding.header_placeholder') }}"
                        >
                        @error('qrBrandingHeaderText') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('headerText') <p class="wp-error">{{ $message }}</p> @enderror
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.branding.header_hint') }}</p>
                    </div>
                </div>
            </div>

            <div class="wp-disclosure-block">
                <button
                    type="button"
                    class="wp-disclosure-block-toggle wp-team-row-toggle"
                    @click="openBackground = !openBackground"
                    :aria-expanded="openBackground"
                >
                    <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': openBackground }" />
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.branding.section_background') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openBackground" x-cloak>
                    <div class="wp-field">
                        <label class="wp-label" for="qrBrandingBackgroundPreset">{{ __('settings.qr_stickers.branding.preset_label') }}</label>
                        <select
                            id="qrBrandingBackgroundPreset"
                            class="wp-input"
                            wire:model.live="qrBrandingBackgroundPreset"
                        >
                            @foreach ($qrPrintableBackgroundPresets as $preset)
                                <option value="{{ $preset['value'] }}">{{ $preset['label'] }}</option>
                            @endforeach
                        </select>
                        @error('preset') <p class="wp-error">{{ $message }}</p> @enderror
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.branding.preset_hint') }}</p>
                    </div>

                    <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.branding.background_hint') }}</p>
                    <p class="wp-muted wp-text-sm" wire:loading wire:target="qrBrandingBackground">
                        {{ __('settings.qr_stickers.branding.background_uploading') }}
                    </p>
                    <x-wp-file-input wireModel="qrBrandingBackground" id="qrBrandingBackground" accept="image/*" />
                    @error('qrBrandingBackground') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('background') <p class="wp-error">{{ $message }}</p> @enderror
                    @if ($hasCustomBackground)
                        <div class="wp-cluster">
                            <button
                                type="button"
                                class="btn btn--ghost btn--sm"
                                wire:click="removeQrBrandingBackground"
                                wire:confirm="{{ __('settings.qr_stickers.branding.background_remove_confirm') }}"
                            >
                                {{ __('settings.qr_stickers.branding.background_remove') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <div class="wp-disclosure-block">
                <button
                    type="button"
                    class="wp-disclosure-block-toggle wp-team-row-toggle"
                    @click="openLogo = !openLogo"
                    :aria-expanded="openLogo"
                >
                    <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': openLogo }" />
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.branding.section_logo') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openLogo" x-cloak>
                    <div class="wp-field">
                        <label class="wp-label" for="qrBrandingTenantLogo">{{ __('settings.qr_stickers.branding.tenant_logo_label') }}</label>
                        <select id="qrBrandingTenantLogo" class="wp-input" wire:model.live="qrBrandingTenantLogo">
                            @foreach ($qrStickerTenantLogoChoices as $choice)
                                <option value="{{ $choice->value }}">{{ __('settings.qr_stickers.branding.tenant_logo_'.$choice->value) }}</option>
                            @endforeach
                        </select>
                        @error('tenantLogo') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="wp-field">
                        <label class="wp-label" for="qrBrandingTenantAddress">{{ __('settings.qr_stickers.branding.tenant_address_label') }}</label>
                        <select id="qrBrandingTenantAddress" class="wp-input" wire:model.live="qrBrandingTenantAddress">
                            @foreach ($qrStickerTenantLogoChoices as $choice)
                                <option value="{{ $choice->value }}">{{ __('settings.qr_stickers.branding.tenant_logo_'.$choice->value) }}</option>
                            @endforeach
                        </select>
                        @error('tenantAddress') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.branding.save_hint') }}</p>
            <div class="wp-cluster">
                <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
            </div>
        </form>
    </div>

    <aside class="wp-settings-split-preview wp-qr-sticker-preview" aria-label="{{ __('settings.qr_stickers.branding.preview_label') }}">
        <p class="wp-label">{{ __('settings.qr_stickers.branding.preview_label') }}</p>
        <div wire:loading wire:target="{{ $previewTargets }}">
            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.branding.preview_loading') }}</p>
        </div>
        @if ($qrBrandingPreviewDataUrl)
            <img
                src="{{ $qrBrandingPreviewDataUrl }}"
                alt="{{ __('settings.qr_stickers.branding.preview_alt') }}"
                class="wp-qr-sticker-preview-img"
                width="182"
                height="262"
                wire:loading.remove
                wire:target="{{ $previewTargets }}"
            >
        @else
            <p class="wp-muted wp-text-sm" wire:loading.remove wire:target="{{ $previewTargets }}">
                {{ __('settings.qr_stickers.branding.preview_unavailable') }}
            </p>
        @endif
    </aside>
</div>
