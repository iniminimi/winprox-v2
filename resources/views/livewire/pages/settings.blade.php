<div class="wp-stack" data-manual-capture="settings" x-data x-on:ui-theme-changed.window="document.documentElement.dataset.theme = $event.detail.theme">
    <x-wp-page-head-title
        icon="settings"
        :title="__('settings.title')"
        help-page="settings"
        :subtitle="__('settings.subtitle')"
    />

    <div
        class="wp-card wp-card-pad wp-stack-tight wp-settings-section"
        x-data="{ open: false }"
        wire:key="settings-config-overview"
    >
        <button
            type="button"
            class="wp-settings-section-toggle wp-settings-section-toggle--stacked"
            @click="open = !open; if (open) $wire.loadConfigOverview()"
            :aria-expanded="open"
        >
            <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
            <span class="wp-grow wp-stack-tight">
                <span class="wp-cluster">
                    <h2 class="wp-section-title">{{ __('settings.config_overview.title') }}</h2>
                    @if ($configIssueCount > 0)
                        <span class="wp-pill wp-pill--closed">{{ trans_choice('health.widget.issues', $configIssueCount, ['count' => $configIssueCount]) }}</span>
                    @endif
                </span>
                <span class="wp-muted" x-show="!open">{{ __('settings.config_overview.subtitle_collapsed') }}</span>
            </span>
        </button>
        <div class="wp-disclosure-panel wp-stack" x-show="open" x-cloak>
            @if ($configOverviewLoaded && $configSummary instanceof \App\Support\Admin\AdminConfigSummary)
                @include('livewire.pages.partials.settings-config-overview', ['configSummary' => $configSummary])
            @else
                <p class="wp-muted">{{ __('settings.config_overview.loading') }}</p>
            @endif
        </div>
    </div>

    @if ($organisationTenant)
        <x-wp-settings-section :title="__('settings.org.title')">
            <div class="wp-stack wp-settings-subblocks">
                <div class="wp-settings-subblock">
                    <h3 class="wp-settings-subblock-title">{{ __('settings.org.details_label') }}</h3>
                    @if ($canManageOrganisation)
                        <p class="wp-muted wp-text-sm">{{ __('settings.org.card_hint') }}</p>
                        <div class="wp-org-identity">
                            <p class="wp-org-identity-name">{{ $organisationTenant->name }}</p>
                            @if ($organisationTenant->email)
                                <p>{{ __('settings.org.label_email') }}: {{ $organisationTenant->email }}</p>
                            @endif
                            @if ($organisationTenant->phone)
                                <p>{{ __('settings.org.label_phone') }}: {{ $organisationTenant->phone }}</p>
                            @endif
                            @if ($organisationTenant->organisationAddressLine())
                                <p>{{ $organisationTenant->organisationAddressLine() }}</p>
                            @endif
                        </div>
                        <div class="wp-cluster">
                            <button type="button" class="btn btn--primary btn--sm" wire:click="openOrgModal">
                                {{ __('settings.org.edit') }}
                            </button>
                        </div>
                    @else
                        <p class="wp-muted">{{ __('settings.org.readonly_hint') }}</p>
                        <div class="wp-org-identity">
                            <p class="wp-org-identity-name">{{ $organisationTenant->name }}</p>
                            @if ($organisationTenant->email)
                                <p>{{ __('settings.org.label_email') }}: {{ $organisationTenant->email }}</p>
                            @endif
                            @if ($organisationTenant->phone)
                                <p>{{ __('settings.org.label_phone') }}: {{ $organisationTenant->phone }}</p>
                            @endif
                            @if ($organisationTenant->organisationAddressLine())
                                <p>{{ $organisationTenant->organisationAddressLine() }}</p>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="wp-settings-subblock">
                    <h3 class="wp-settings-subblock-title">{{ __('settings.org.logo_label') }}</h3>
                    @if ($canManageOrganisation)
                        <p class="wp-muted wp-text-sm">{{ __('settings.org.logo_hint') }}</p>
                        <div class="wp-field">
                            @if ($organisationLogoUrl)
                                <img
                                    src="{{ $organisationLogoUrl }}"
                                    alt="{{ __('settings.org.logo_preview_alt') }}"
                                    class="wp-org-logo-preview"
                                    width="120"
                                    height="120"
                                    wire:key="org-logo-inline-{{ md5($organisationLogoUrl) }}"
                                >
                            @endif
                            <x-wp-file-input wireModel="orgLogo" id="orgLogoInline" accept="image/*" />
                            @error('orgLogo') <p class="wp-error">{{ $message }}</p> @enderror
                        </div>
                    @else
                        @if ($organisationLogoUrl)
                            <img
                                src="{{ $organisationLogoUrl }}"
                                alt="{{ __('settings.org.logo_preview_alt') }}"
                                class="wp-org-logo-preview"
                                width="120"
                                height="120"
                                wire:key="org-logo-readonly-{{ md5($organisationLogoUrl) }}"
                            >
                        @endif
                    @endif
                </div>
            </div>
        </x-wp-settings-section>
    @endif

    <x-wp-settings-section :title="__('settings.notifications.title')">
        <p class="wp-muted wp-text-sm">{{ __('settings.notifications.hint') }}</p>
        <label class="wp-check wp-check--boxed">
            <input type="checkbox" wire:model.live="notifyOnNewIssueEmail" class="wp-checkbox">
            <span>
                {{ __('settings.notifications.new_qr_issue_label') }}
                <br><span class="wp-hint">{{ __('settings.notifications.new_qr_issue_hint') }}</span>
            </span>
        </label>
    </x-wp-settings-section>

    <x-wp-settings-section :title="__('settings.style.title')">
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
    </x-wp-settings-section>

    @if ($canUpdateTenantBranding && $organisationTenant)
        <x-wp-settings-section :title="__('settings.org.custom_theme_title')">
            <div class="wp-stack wp-settings-subblocks">
                <div class="wp-settings-subblock">
                    <h3 class="wp-settings-subblock-title">{{ __('settings.org.portal_background_label') }}</h3>
                    <p class="wp-muted wp-text-sm">{{ __('settings.org.portal_background_hint') }}</p>
                    <div class="wp-field">
                        @if ($portalBackgroundUrl)
                            <img
                                src="{{ $portalBackgroundUrl }}"
                                alt="{{ __('settings.org.portal_background_preview_alt') }}"
                                class="wp-portal-bg-preview"
                                wire:key="portal-bg-inline-{{ md5($portalBackgroundUrl) }}"
                            >
                        @endif
                        <x-wp-file-input wireModel="portalBackground" id="portalBackgroundInline" accept="image/*" />
                        @error('portalBackground') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="wp-settings-subblock">
                    <h3 class="wp-settings-subblock-title">{{ __('settings.org.portal_colors_label') }}</h3>
                    <form wire:submit="saveOrganisationInline" class="wp-stack-tight">
                        <div class="wp-settings-split">
                            <div class="wp-stack-tight">
                                <div class="wp-field">
                                    <label class="wp-label" for="customThemeBgInline">{{ __('settings.org.custom_theme_bg_label') }}</label>
                                    <div class="wp-color-input-row">
                                        <input type="color" id="customThemeBgInline" wire:model.live="customThemeBg" class="wp-color-input-swatch">
                                        <input type="text" wire:model.live="customThemeBg" class="wp-input wp-color-input-hex" placeholder="#e7e8ec" pattern="^#[a-fA-F0-9]{6}$">
                                    </div>
                                    @error('customThemeBg') <p class="wp-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="wp-field">
                                    <label class="wp-label" for="customThemeBtnInline">{{ __('settings.org.custom_theme_btn_label') }}</label>
                                    <div class="wp-color-input-row">
                                        <input type="color" id="customThemeBtnInline" wire:model.live="customThemeBtn" class="wp-color-input-swatch">
                                        <input type="text" wire:model.live="customThemeBtn" class="wp-input wp-color-input-hex" placeholder="#059669" pattern="^#[a-fA-F0-9]{6}$">
                                    </div>
                                    @error('customThemeBtn') <p class="wp-error">{{ $message }}</p> @enderror
                                </div>

                                <div class="wp-cluster wp-settings-theme-save">
                                    <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
                                </div>
                            </div>

                            <div class="wp-settings-split-preview wp-theme-preview">
                                <div
                                    class="wp-theme-preview-card"
                                    x-data="{
                                        bg: @js($customThemeBg),
                                        btn: @js($customThemeBtn)
                                    }"
                                    x-init="$watch('$wire.customThemeBg', (val) => { bg = val; }); $watch('$wire.customThemeBtn', (val) => { btn = val; });"
                                    :style="{
                                        backgroundColor: bg,
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
                                            :style="{ backgroundColor: btn, borderColor: btn }">
                                            {{ __('settings.org.theme_preview_report') }}
                                        </button>
                                        <button type="button" class="btn btn--primary btn--sm btn--block wp-theme-preview-btn"
                                            :style="{ backgroundColor: btn, borderColor: btn }">
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
                </div>
            </div>
        </x-wp-settings-section>

        <x-wp-settings-section :title="__('settings.qr_stickers.title')">
            <p class="wp-muted wp-text-sm">{{ __('settings.qr_stickers.hint') }}</p>
            @include('livewire.pages.partials.qr-sticker-avery6289-editor')
            @include('livewire.pages.partials.qr-printable-page-editor')
        </x-wp-settings-section>
    @endif

    @if ($canManageOrganisation)
        <x-wp-settings-section
            id="settings-privacy"
            :title="__('settings.privacy.title')"
            :open-by-default="request()->query('open') === 'privacy'"
        >
            <p class="wp-muted">{{ __('settings.privacy.hint') }}</p>
            <div
                x-data="{
                    downloading: false,
                    error: null,
                    async downloadExport() {
                        if (this.downloading) {
                            return;
                        }

                        this.downloading = true;
                        this.error = null;

                        try {
                            await window.wpDownloadAuthenticatedFile(
                                @js(route('account.data-export')),
                                'application/zip,*/*',
                            );
                        } catch (exception) {
                            this.error = exception?.message || @js(__('settings.privacy.download_failed'));
                        } finally {
                            this.downloading = false;
                        }
                    },
                }"
            >
                <button
                    type="button"
                    class="btn btn--ghost btn--sm"
                    @click="downloadExport()"
                    :disabled="downloading"
                    :aria-busy="downloading"
                >
                    <span class="wp-cluster" x-show="downloading" x-cloak>
                        <x-wp-hourglass size="sm" :visible="true" />
                        <span>{{ __('settings.privacy.preparing') }}</span>
                    </span>
                    <span x-show="!downloading">{{ __('settings.privacy.download') }}</span>
                </button>
                <p class="wp-error wp-text-sm" x-show="error" x-text="error" x-cloak></p>
            </div>
        </x-wp-settings-section>
    @endif

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
