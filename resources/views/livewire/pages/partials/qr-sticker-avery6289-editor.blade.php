<div class="wp-settings-split wp-qr-sticker-editor">
    <div class="wp-qr-sticker-editor-controls wp-stack-tight">
        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.hint') }}</p>
        <h3 class="wp-issue-card-title">{{ __('settings.qr_stickers.avery_62x89_r.title') }}</h3>
        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.hint') }}</p>

        <form
            wire:submit="saveQrStickerAvery6289Settings"
            class="wp-stack-tight"
            x-data="{ openText: false, openLogo: false, openBackground: false }"
        >
            <div class="wp-disclosure-block">
                <button
                    type="button"
                    class="wp-disclosure-block-toggle wp-team-row-toggle"
                    @click="openText = !openText"
                    :aria-expanded="openText"
                >
                    <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': openText }" />
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.avery_62x89_r.section_text') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openText" x-cloak>
                    <div class="wp-field">
                        <label class="wp-label" for="qrStickerAvery6289HeaderText">{{ __('settings.qr_stickers.avery_62x89_r.header_label') }}</label>
                        <input
                            type="text"
                            id="qrStickerAvery6289HeaderText"
                            class="wp-input"
                            wire:model.live.debounce.500ms="qrStickerAvery6289HeaderText"
                            maxlength="{{ \App\Support\Qr\Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS }}"
                            placeholder="{{ __('settings.qr_stickers.avery_62x89_r.header_placeholder') }}"
                        >
                        @error('qrStickerAvery6289HeaderText') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('headerText') <p class="wp-error">{{ $message }}</p> @enderror
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.header_hint') }}</p>
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
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.avery_62x89_r.section_logo') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openLogo" x-cloak>
                    <div class="wp-field">
                        <label class="wp-label" for="qrStickerAvery6289TenantLogo">{{ __('settings.qr_stickers.avery_62x89_r.tenant_logo_label') }}</label>
                        <select id="qrStickerAvery6289TenantLogo" class="wp-input" wire:model.live="qrStickerAvery6289TenantLogo">
                            @foreach ($qrStickerTenantLogoChoices as $choice)
                                <option value="{{ $choice->value }}">{{ __('settings.qr_stickers.avery_62x89_r.tenant_logo_'.$choice->value) }}</option>
                            @endforeach
                        </select>
                        @error('tenantLogo') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="wp-field">
                        <label class="wp-label" for="qrStickerAvery6289TenantAddress">{{ __('settings.qr_stickers.avery_62x89_r.tenant_address_label') }}</label>
                        <select id="qrStickerAvery6289TenantAddress" class="wp-input" wire:model.live="qrStickerAvery6289TenantAddress">
                            @foreach ($qrStickerTenantLogoChoices as $choice)
                                <option value="{{ $choice->value }}">{{ __('settings.qr_stickers.avery_62x89_r.tenant_logo_'.$choice->value) }}</option>
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
                    <span class="wp-data-row-title">{{ __('settings.qr_stickers.avery_62x89_r.section_background') }}</span>
                </button>
                <div class="wp-disclosure-panel wp-stack-tight" x-show="openBackground" x-cloak>
                    <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.background_hint') }}</p>
                    <p class="wp-muted wp-text-sm" wire:loading wire:target="qrStickerAvery6289Background">
                        {{ __('settings.qr_stickers.avery_62x89_r.background_uploading') }}
                    </p>
                    <x-wp-file-input wireModel="qrStickerAvery6289Background" id="qrStickerAvery6289Background" accept="image/*" />
                    @error('qrStickerAvery6289Background') <p class="wp-error">{{ $message }}</p> @enderror
                    @error('background') <p class="wp-error">{{ $message }}</p> @enderror
                    @if (filled($organisationTenant->qrStickerSheetSetting(\App\Support\Qr\QrStickerSheetTemplate::Avery62x89R)?->background_path))
                        <div class="wp-cluster">
                            <button
                                type="button"
                                class="btn btn--ghost btn--sm"
                                wire:click="removeQrStickerAvery6289Background"
                                wire:confirm="{{ __('settings.qr_stickers.avery_62x89_r.background_remove_confirm') }}"
                            >
                                {{ __('settings.qr_stickers.avery_62x89_r.background_remove') }}
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.save_hint') }}</p>
            <div class="wp-cluster">
                <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
            </div>
        </form>
    </div>

    <aside class="wp-settings-split-preview wp-qr-sticker-preview" aria-label="{{ __('settings.qr_stickers.avery_62x89_r.preview_label') }}">
        <p class="wp-label">{{ __('settings.qr_stickers.avery_62x89_r.preview_label') }}</p>
        <div wire:loading wire:target="qrStickerAvery6289HeaderText, qrStickerAvery6289TenantLogo, qrStickerAvery6289TenantAddress, qrStickerAvery6289Background, saveQrStickerAvery6289Settings, removeQrStickerAvery6289Background">
            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.preview_loading') }}</p>
        </div>
        @if ($qrStickerPreviewDataUrl)
            <img
                src="{{ $qrStickerPreviewDataUrl }}"
                alt="{{ __('settings.qr_stickers.avery_62x89_r.preview_alt') }}"
                class="wp-qr-sticker-preview-img"
                width="182"
                height="262"
                wire:loading.remove
                wire:target="qrStickerAvery6289HeaderText, qrStickerAvery6289TenantLogo, qrStickerAvery6289TenantAddress, qrStickerAvery6289Background, saveQrStickerAvery6289Settings, removeQrStickerAvery6289Background"
            >
        @else
            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.preview_unavailable') }}</p>
        @endif
    </aside>
</div>
