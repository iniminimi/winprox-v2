<div class="wp-stack" x-data x-on:ui-theme-changed.window="document.documentElement.dataset.theme = $event.detail.theme">
    <x-wp-page-head-title
        icon="settings"
        :title="__('settings.title')"
        help-page="settings"
        :subtitle="__('settings.subtitle')"
    />

    @if ($canManageOrganisation && $organisationTenant)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.title') }}</h2>
            <p class="wp-muted wp-text-sm">{{ __('settings.org.card_hint') }}</p>
            <p class="wp-text-body"><strong>{{ $organisationTenant->name }}</strong></p>
            <div class="wp-stack-tight wp-text-sm">
                @if ($organisationTenant->email)
                    <p class="wp-muted">
                        <span class="wp-text-body">{{ __('settings.org.label_email') }}:</span>
                        {{ $organisationTenant->email }}
                    </p>
                @endif
                @if ($organisationTenant->phone)
                    <p class="wp-muted">
                        <span class="wp-text-body">{{ __('settings.org.label_phone') }}:</span>
                        {{ $organisationTenant->phone }}
                    </p>
                @endif
                @if ($organisationTenant->organisationAddressLine())
                    <p class="wp-muted">
                        <span class="wp-text-body">{{ __('settings.org.label_address') }}:</span>
                        {{ $organisationTenant->organisationAddressLine() }}
                    </p>
                @endif
            </div>
            <div class="wp-cluster">
                <button type="button" class="btn btn--primary btn--sm" wire:click="openOrgModal">
                    {{ __('settings.org.edit') }}
                </button>
            </div>
        </div>
    @elseif ($canManageOrganisation)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.title') }}</h2>
            <div class="wp-cluster">
                <button type="button" class="btn btn--primary btn--sm" wire:click="openOrgModal">
                    {{ __('settings.org.edit') }}
                </button>
            </div>
        </div>
    @else
        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.title') }}</h2>
            <p class="wp-muted">{{ __('settings.org.readonly_hint') }}</p>
            @php $readonlyTenant = auth()->user()->tenant; @endphp
            @if ($readonlyTenant?->name)
                <p><strong>{{ $readonlyTenant->name }}</strong></p>
                @if ($readonlyTenant->email)
                    <p class="wp-muted wp-text-sm">{{ __('settings.org.label_email') }}: {{ $readonlyTenant->email }}</p>
                @endif
                @if ($readonlyTenant->phone)
                    <p class="wp-muted wp-text-sm">{{ __('settings.org.label_phone') }}: {{ $readonlyTenant->phone }}</p>
                @endif
                @if ($readonlyTenant->organisationAddressLine())
                    <p class="wp-muted wp-text-sm">{{ __('settings.org.label_address') }}: {{ $readonlyTenant->organisationAddressLine() }}</p>
                @endif
            @endif
        </div>
    @endif

    @if ($canManageOrganisation && $organisationTenant)
        <form wire:submit="saveOrganisationLogo" class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.logo_label') }}</h2>
            <p class="wp-muted wp-text-sm">{{ __('settings.org.logo_hint') }}</p>
            <div class="wp-field">
                @if ($organisationLogoUrl)
                    <img
                        src="{{ $organisationLogoUrl }}"
                        alt="{{ __('settings.org.logo_preview_alt') }}"
                        class="wp-org-logo-preview"
                        width="120"
                        height="120"
                        style="margin-bottom: 0.75rem;"
                        wire:key="org-logo-inline-{{ md5($organisationLogoUrl) }}"
                    >
                @endif
                <x-wp-file-input wireModel="orgLogo" id="orgLogoInline" accept="image/*" />
                @error('orgLogo') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-cluster">
                <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
            </div>
        </form>

        <form wire:submit="saveOrganisationPortalBackground" class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.portal_background_label') }}</h2>
            <p class="wp-muted wp-text-sm">{{ __('settings.org.portal_background_hint') }}</p>
            <div class="wp-field">
                @if ($portalBackgroundUrl)
                    <img
                        src="{{ $portalBackgroundUrl }}"
                        alt="{{ __('settings.org.portal_background_preview_alt') }}"
                        class="wp-portal-bg-preview"
                        style="max-height: 150px; width: auto; margin-bottom: 0.75rem;"
                        wire:key="portal-bg-inline-{{ md5($portalBackgroundUrl) }}"
                    >
                @endif
                <x-wp-file-input wireModel="portalBackground" id="portalBackgroundInline" accept="image/*" />
                @error('portalBackground') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-cluster">
                <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
            </div>
        </form>
    @endif

    <div class="wp-card wp-card-pad wp-stack-tight">
        <h2 class="wp-section-title">{{ __('settings.style.title') }}</h2>
        <p class="wp-muted">{{ __('settings.style.hint') }}</p>
        <div class="wp-style-options" role="radiogroup" aria-label="{{ __('settings.style.title') }}">
            @foreach ($themeChoices as $choice)
                <label class="wp-style-option {{ $uiTheme === $choice->value ? 'is-selected' : '' }}">
                    <input
                        type="radio"
                        name="uiTheme"
                        value="{{ $choice->value }}"
                        wire:model.live="uiTheme"
                        class="wp-style-option-input"
                    >
                    <span class="wp-style-option-label">{{ __('settings.style.options.'.$choice->value.'.label') }}</span>
                    <span class="wp-style-option-desc">{{ __('settings.style.options.'.$choice->value.'.description') }}</span>
                </label>
            @endforeach
        </div>
    </div>

    @if ($canManageOrganisation && $organisationTenant)
        <form wire:submit="saveOrganisationInline" class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.custom_theme_title') }}</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: var(--wp-space-2);">
                <div class="wp-stack-tight">
                    <label class="wp-checkbox-label">
                        <input type="checkbox" wire:model.live="customThemeActive" class="wp-checkbox">
                        <span>{{ __('settings.org.custom_theme_active_label') }}</span>
                    </label>
                    
                    @if($customThemeActive)
                        <div class="wp-stack-tight" style="margin-top: var(--wp-space-3);">
                            <div class="wp-field">
                                <label class="wp-label" for="customThemeBgInline">{{ __('settings.org.custom_theme_bg_label') }}</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="color" id="customThemeBgInline" wire:model.live="customThemeBg" class="wp-input" style="width: 3rem; padding: 0.25rem;">
                                    <input type="text" wire:model.live="customThemeBg" class="wp-input" placeholder="#e7e8ec" pattern="^#[a-fA-F0-9]{6}$" style="flex: 1; max-width: 150px;">
                                </div>
                                @error('customThemeBg') <p class="wp-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="wp-field">
                                <label class="wp-label" for="customThemeBtnInline">{{ __('settings.org.custom_theme_btn_label') }}</label>
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="color" id="customThemeBtnInline" wire:model.live="customThemeBtn" class="wp-input" style="width: 3rem; padding: 0.25rem;">
                                    <input type="text" wire:model.live="customThemeBtn" class="wp-input" placeholder="#059669" pattern="^#[a-fA-F0-9]{6}$" style="flex: 1; max-width: 150px;">
                                </div>
                                @error('customThemeBtn') <p class="wp-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @endif

                    <div class="wp-cluster" style="margin-top: var(--wp-space-3);">
                        <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
                    </div>
                </div>

                <div class="wp-theme-preview" style="margin-top: 0;">
                    <div 
                        class="wp-theme-preview-card"
                        x-data="{ 
                            bg: @js($customThemeBg),
                            btn: @js($customThemeBtn),
                            active: @js($customThemeActive)
                        }"
                        x-on:custom-theme-updated.window="bg = $event.detail.bg; btn = $event.detail.btn; active = $event.detail.active"
                        @if($customThemeActive)
                            x-init="$watch('$wire.customThemeBg', (val) => { bg = val; window.dispatchEvent(new CustomEvent('custom-theme-updated', { detail: { bg: val, btn: $wire.customThemeBtn, active: $wire.customThemeActive } })); }); $watch('$wire.customThemeBtn', (val) => { btn = val; window.dispatchEvent(new CustomEvent('custom-theme-updated', { detail: { bg: $wire.customThemeBg, btn: val, active: $wire.customThemeActive } })); }); $watch('$wire.customThemeActive', (val) => { active = val; window.dispatchEvent(new CustomEvent('custom-theme-updated', { detail: { bg: $wire.customThemeBg, btn: $wire.customThemeBtn, active: val } })); });"
                        @endif
                        :style="{
                            backgroundColor: active ? bg : '#e7e8ec',
                            border: '8px solid #1f2937',
                            borderRadius: '24px',
                            padding: '1rem',
                            minHeight: '280px',
                            maxWidth: '200px',
                            margin: '0 auto',
                            boxShadow: '0 4px 6px rgba(0, 0, 0, 0.1)'
                        }"
                    >
                        <div class="wp-theme-preview-header">
                            <div class="wp-theme-preview-title">{{ __('settings.org.theme_preview_title') }}</div>
                            <div class="wp-theme-preview-subtitle">{{ __('settings.org.theme_preview_subtitle') }}</div>
                        </div>
                        <div class="wp-theme-preview-actions">
                            <button type="button" class="btn btn--primary btn--sm btn--block wp-theme-preview-btn"
                                :style="active ? { backgroundColor: btn, borderColor: btn } : {}">
                                {{ __('settings.org.theme_preview_report') }}
                            </button>
                            <button type="button" class="btn btn--primary btn--sm btn--block wp-theme-preview-btn"
                                :style="active ? { backgroundColor: btn, borderColor: btn } : {}">
                                {{ __('settings.org.theme_preview_issues') }}
                            </button>
                            <div class="wp-theme-preview-card">
                                <div class="wp-theme-preview-card-label">{{ __('settings.org.theme_preview_issue_label') }}</div>
                                <div class="wp-theme-preview-card-title">{{ __('settings.org.theme_preview_issue_title') }}</div>
                                <div class="wp-theme-preview-card-desc">{{ __('settings.org.theme_preview_issue_desc') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.qr_stickers.title') }}</h2>
            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.hint') }}</p>

            <div class="wp-stack-tight">
                <h3 class="wp-issue-card-title">{{ __('settings.qr_stickers.avery_62x89_r.title') }}</h3>
                <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.hint') }}</p>

                <form wire:submit="saveQrStickerAvery6289Settings" class="wp-stack-tight">
                    <div class="wp-field">
                        <label class="wp-label" for="qrStickerAvery6289HeaderText">{{ __('settings.qr_stickers.avery_62x89_r.header_label') }}</label>
                        <input
                            type="text"
                            id="qrStickerAvery6289HeaderText"
                            class="wp-input"
                            wire:model="qrStickerAvery6289HeaderText"
                            maxlength="{{ \App\Support\Qr\Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS }}"
                            placeholder="{{ __('settings.qr_stickers.avery_62x89_r.header_placeholder') }}"
                        >
                        @error('qrStickerAvery6289HeaderText') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('headerText') <p class="wp-error">{{ $message }}</p> @enderror
                        <p class="wp-muted wp-text-sm">
                            <span class="wp-text-body">{{ __('settings.qr_stickers.avery_62x89_r.current_text_label') }}:</span>
                            @if (filled($organisationTenant->qrStickerSheetSetting(\App\Support\Qr\QrStickerSheetTemplate::Avery62x89R)?->header_text))
                                {{ $organisationTenant->qrStickerSheetSetting(\App\Support\Qr\QrStickerSheetTemplate::Avery62x89R)->header_text }}
                            @else
                                {{ __('settings.qr_stickers.avery_62x89_r.current_text_empty') }}
                            @endif
                        </p>
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.header_help') }}</p>
                    </div>

                    <div class="wp-field wp-stack-tight">
                        <p class="wp-label">{{ __('settings.qr_stickers.avery_62x89_r.layout_title') }}</p>
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.layout_hint') }}</p>

                        <fieldset class="wp-stack-tight">
                            <legend class="wp-label">{{ __('settings.qr_stickers.avery_62x89_r.center_logo_label') }}</legend>
                            @foreach ($qrStickerCenterLogoChoices as $choice)
                                <label class="wp-checkbox-label">
                                    <input
                                        type="radio"
                                        class="wp-checkbox"
                                        wire:model="qrStickerAvery6289CenterLogo"
                                        value="{{ $choice->value }}"
                                    >
                                    {{ __('settings.qr_stickers.avery_62x89_r.center_logo_'.$choice->value) }}
                                </label>
                            @endforeach
                            @error('centerLogo') <p class="wp-error">{{ $message }}</p> @enderror
                        </fieldset>

                        <label class="wp-checkbox-label">
                            <input type="checkbox" wire:model="qrStickerAvery6289CornerTenantLogo" class="wp-checkbox">
                            {{ __('settings.qr_stickers.avery_62x89_r.corner_logo_label') }}
                        </label>
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.corner_logo_help') }}</p>
                        @error('cornerTenantLogo') <p class="wp-error">{{ $message }}</p> @enderror

                        <label class="wp-checkbox-label">
                            <input type="checkbox" wire:model="qrStickerAvery6289ShowTenantAddress" class="wp-checkbox">
                            {{ __('settings.qr_stickers.avery_62x89_r.tenant_address_label') }}
                        </label>
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.tenant_address_help') }}</p>
                        @error('showTenantAddress') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="wp-cluster">
                        <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
                    </div>
                </form>

                <form wire:submit="saveQrStickerAvery6289Background" class="wp-stack-tight">
                    <div class="wp-field">
                        <p class="wp-label">{{ __('settings.qr_stickers.avery_62x89_r.background_label') }}</p>
                        <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.background_hint') }}</p>
                        @if ($qrStickerAvery6289BackgroundUrl)
                            <img
                                src="{{ $qrStickerAvery6289BackgroundUrl }}"
                                alt="{{ __('settings.qr_stickers.avery_62x89_r.background_preview_alt') }}"
                                class="wp-portal-bg-preview"
                                style="max-height: 180px; width: auto; margin-bottom: 0.75rem;"
                                wire:key="qr-sticker-bg-{{ md5($qrStickerAvery6289BackgroundUrl) }}"
                            >
                        @else
                            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.avery_62x89_r.background_default_label') }}</p>
                        @endif
                        <x-wp-file-input wireModel="qrStickerAvery6289Background" id="qrStickerAvery6289Background" accept="image/*" />
                        @error('qrStickerAvery6289Background') <p class="wp-error">{{ $message }}</p> @enderror
                        @error('background') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-cluster">
                        <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
                        @if (filled($organisationTenant->qrStickerSheetSetting(\App\Support\Qr\QrStickerSheetTemplate::Avery62x89R)?->background_path))
                            <button
                                type="button"
                                class="btn btn--ghost btn--sm"
                                wire:click="removeQrStickerAvery6289Background"
                                wire:confirm="{{ __('settings.qr_stickers.avery_62x89_r.background_remove_confirm') }}"
                            >
                                {{ __('settings.qr_stickers.avery_62x89_r.background_remove') }}
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack-tight">
        <h2 class="wp-section-title">{{ __('settings.privacy.title') }}</h2>
        <p class="wp-muted">{{ __('settings.privacy.hint') }}</p>
        <p>
            <a href="{{ route('account.data-export') }}" class="btn btn--ghost btn--sm">{{ __('settings.privacy.download') }}</a>
        </p>
    </div>

    @if ($canManageOrganisation && $showOrgModal)
        @teleport('body')
        <x-wp-modal closeMethod="closeOrgModal" aria-labelledby="org-edit-title">
            <form wire:submit="saveOrganisation" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <div class="wp-stack-tight">
                        <h2 id="org-edit-title" class="wp-section-title">{{ __('settings.org.modal_title') }}</h2>
                        <p class="wp-muted wp-text-sm">{{ __('settings.org.modal_hint') }}</p>
                    </div>
                    <x-wp-modal-close wire:click="closeOrgModal" />
                </div>

                <div class="wp-modal-body wp-stack">
                    <div class="wp-field">
                        <input type="text" id="orgName" class="wp-input" wire:model="orgName"
                               placeholder="{{ __('settings.org.placeholder_name') }}" autocomplete="organization">
                        @error('name') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <input type="email" id="orgEmail" class="wp-input" wire:model="orgEmail"
                               placeholder="{{ __('settings.org.placeholder_email') }}" autocomplete="email">
                        @error('email') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <input type="text" id="orgPhone" class="wp-input" wire:model="orgPhone"
                               placeholder="{{ __('settings.org.placeholder_phone') }}" autocomplete="tel">
                        @error('phone') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <input type="text" id="orgStreet" class="wp-input" wire:model="orgStreet"
                               placeholder="{{ __('settings.org.placeholder_street') }}" autocomplete="street-address">
                        @error('street') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-form-grid-2">
                        <div class="wp-field">
                            <input type="text" id="orgHouseNumber" class="wp-input" wire:model="orgHouseNumber"
                                   placeholder="{{ __('settings.org.placeholder_house_number') }}">
                            @error('house_number') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="wp-field">
                            <input type="text" id="orgPostalCode" class="wp-input" wire:model="orgPostalCode"
                                   placeholder="{{ __('settings.org.placeholder_postal_code') }}" autocomplete="postal-code">
                            @error('postal_code') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div class="wp-field">
                        <input type="text" id="orgCity" class="wp-input" wire:model="orgCity"
                               placeholder="{{ __('settings.org.placeholder_city') }}" autocomplete="address-level2">
                        @error('city') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <input type="text" id="orgCountryCode" class="wp-input" wire:model="orgCountryCode"
                               maxlength="2" placeholder="{{ __('settings.org.placeholder_country') }}"
                               autocomplete="country">
                        @error('country_code') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeOrgModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </x-wp-modal>
        @endteleport
    @endif
</div>
