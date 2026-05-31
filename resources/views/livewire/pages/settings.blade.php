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
