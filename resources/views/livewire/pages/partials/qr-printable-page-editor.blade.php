<div class="wp-settings-split wp-qr-sticker-editor">
    <div class="wp-qr-sticker-editor-controls wp-stack-tight">
        <h3 class="wp-issue-card-title">{{ __('settings.qr_stickers.printable_page.title') }}</h3>
        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.printable_page.hint') }}</p>

        <form
            wire:submit="saveQrPrintablePageSettings"
            class="wp-stack-tight"
            x-data="{ openPreset: false, openLogo: false, openBackground: false }"
        >
            <div class="wp-disclosure-block">
                <button
                    type="button"
                    class="wp-disclosure-block-toggle wp-team-row-toggle"
                    @click="openPreset = !openPreset"
                    :aria-expanded="openPreset"
                >
                    <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': openPreset }" />
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.printable_page.section_preset') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openPreset" x-cloak>
                    <div class="wp-field">
                        <label class="wp-label" for="qrPrintableBackgroundPreset">{{ __('settings.qr_stickers.printable_page.preset_label') }}</label>
                        <select
                            id="qrPrintableBackgroundPreset"
                            class="wp-input"
                            wire:model.live="qrPrintableBackgroundPreset"
                        >
                            @foreach ($qrPrintableBackgroundPresets as $preset)
                                <option value="{{ $preset['value'] }}">{{ $preset['label'] }}</option>
                            @endforeach
                        </select>
                        @error('preset') <p class="wp-error">{{ $message }}</p> @enderror
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.printable_page.preset_hint') }}</p>
                    </div>
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
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.printable_page.section_logo') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openLogo" x-cloak>
                    <div class="wp-field">
                        <label class="wp-label" for="qrPrintableTenantLogo">{{ __('settings.qr_stickers.printable_page.tenant_logo_label') }}</label>
                        <select id="qrPrintableTenantLogo" class="wp-input" wire:model.live="qrPrintableTenantLogo">
                            @foreach ($qrStickerTenantLogoChoices as $choice)
                                <option value="{{ $choice->value }}">{{ __('settings.qr_stickers.printable_page.tenant_logo_'.$choice->value) }}</option>
                            @endforeach
                        </select>
                        @error('tenantLogo') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="wp-field">
                        <label class="wp-label" for="qrPrintableTenantAddress">{{ __('settings.qr_stickers.printable_page.tenant_address_label') }}</label>
                        <select id="qrPrintableTenantAddress" class="wp-input" wire:model.live="qrPrintableTenantAddress">
                            @foreach ($qrStickerTenantLogoChoices as $choice)
                                <option value="{{ $choice->value }}">{{ __('settings.qr_stickers.printable_page.tenant_logo_'.$choice->value) }}</option>
                            @endforeach
                        </select>
                        @error('tenantAddress') <p class="wp-error">{{ $message }}</p> @enderror
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
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.printable_page.section_background') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openBackground" x-cloak>
                    <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.printable_page.background_hint') }}</p>
                    <p class="wp-muted wp-text-sm" wire:loading wire:target="qrPrintableBackground">
                        {{ __('settings.qr_stickers.printable_page.background_uploading') }}
                    </p>
                    <x-wp-file-input wireModel="qrPrintableBackground" id="qrPrintableBackground" accept="image/*" />
                    @error('qrPrintableBackground') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('background') <p class="wp-error">{{ $message }}</p> @enderror
                    @if (filled($organisationTenant->qrStickerSheetSetting(\App\Support\Qr\QrStickerSheetTemplate::printablePageSettings())?->background_path))
                        <div class="wp-cluster">
                            <button
                                type="button"
                                class="btn btn--ghost btn--sm"
                                wire:click="removeQrPrintableBackground"
                                wire:confirm="{{ __('settings.qr_stickers.printable_page.background_remove_confirm') }}"
                            >
                                {{ __('settings.qr_stickers.printable_page.background_remove') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.printable_page.save_hint') }}</p>
            <div class="wp-cluster">
                <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
            </div>
        </form>
    </div>

    <aside class="wp-settings-split-preview wp-qr-sticker-preview" aria-label="{{ __('settings.qr_stickers.printable_page.preview_label') }}">
        <p class="wp-label">{{ __('settings.qr_stickers.printable_page.preview_label') }}</p>
        <div wire:loading wire:target="qrPrintableBackgroundPreset, qrPrintableTenantLogo, qrPrintableTenantAddress, qrPrintableBackground, saveQrPrintablePageSettings, removeQrPrintableBackground">
            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.printable_page.preview_loading') }}</p>
        </div>
        @if ($qrPrintableBackgroundPreviewUrl)
            <img
                src="{{ $qrPrintableBackgroundPreviewUrl }}"
                alt="{{ __('settings.qr_stickers.printable_page.preview_alt') }}"
                class="wp-qr-sticker-preview-img wp-qr-printable-preview-img"
                wire:loading.remove
                wire:target="qrPrintableBackgroundPreset, qrPrintableTenantLogo, qrPrintableTenantAddress, qrPrintableBackground, saveQrPrintablePageSettings, removeQrPrintableBackground"
            >
        @else
            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.printable_page.preview_unavailable') }}</p>
        @endif
    </aside>
</div>
