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
            @if ($organisationLogoUrl)
                <img
                    src="{{ $organisationLogoUrl }}"
                    alt=""
                    class="wp-org-logo-preview"
                    width="120"
                    height="120"
                    wire:key="org-logo-card-{{ md5($organisationLogoUrl) }}"
                >
            @endif
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
                        <div style="margin-bottom: 1rem; text-align: center;">
                            <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem; color: '#1f2937';">Portal</div>
                            <div style="font-size: 0.625rem; opacity: 0.7; color: '#6b7280';">Na scan QR-code</div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <button 
                                :style="{
                                    backgroundColor: active ? btn : '#059669',
                                    color: 'white',
                                    border: 'none',
                                    borderRadius: '8px',
                                    padding: '0.75rem 1rem',
                                    fontSize: '0.75rem',
                                    cursor: 'pointer',
                                    fontWeight: '500'
                                }"
                            >
                                Melding maken
                            </button>
                            <button 
                                :style="{
                                    backgroundColor: active ? btn : '#059669',
                                    color: 'white',
                                    border: 'none',
                                    borderRadius: '8px',
                                    padding: '0.75rem 1rem',
                                    fontSize: '0.75rem',
                                    cursor: 'pointer',
                                    fontWeight: '500'
                                }"
                            >
                                Open meldingen
                            </button>
                            <div 
                                :style="{
                                    backgroundColor: 'white',
                                    borderRadius: '8px',
                                    padding: '0.75rem',
                                    marginTop: '0.5rem',
                                    border: '1px solid #e5e7eb'
                                }"
                            >
                                <div style="font-size: 0.625rem; color: '#6b7280'; margin-bottom: 0.25rem;">Voorbeeld melding</div>
                                <div style="font-size: 0.75rem; color: '#1f2937'; font-weight: '500';">Lift defect</div>
                                <div style="font-size: 0.625rem; color: '#6b7280'; margin-top: '0.25rem';">Lift 3 werkt niet</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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
        <div class="wp-modal" role="dialog" aria-modal="true" aria-labelledby="org-edit-title">
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
                    <div class="wp-field">
                        <label class="wp-label" for="orgLogo">{{ __('settings.org.logo_label') }}</label>
                        @if ($organisationLogoUrl)
                            <img
                                src="{{ $organisationLogoUrl }}"
                                alt=""
                                class="wp-org-logo-preview"
                                width="120"
                                height="120"
                                wire:key="org-logo-modal-{{ md5($organisationLogoUrl) }}"
                            >
                        @endif
                        <input type="file" id="orgLogo" class="wp-input" wire:model="orgLogo" accept="image/*">
                        @error('orgLogo') <p class="wp-error">{{ $message }}</p> @enderror
                        <p class="wp-hint">{{ __('settings.org.logo_hint') }}</p>
                    </div>
                </div>

                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeOrgModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('common.button.save') }}</button>
                </div>
            </form>
        </div>
        @endteleport
    @endif
</div>
