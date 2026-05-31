<div class="wp-stack" x-data x-on:ui-theme-changed.window="document.documentElement.dataset.theme = $event.detail.theme">
    <x-wp-page-head-title
        icon="settings"
        :title="__('settings.title')"
        help-page="settings"
        :subtitle="__('settings.subtitle')"
    />

    @if ($canManageOrganisation)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.title') }}</h2>
            <p class="wp-text-body"><strong>{{ $orgDisplayName }}</strong></p>
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
    @else
        <div class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.title') }}</h2>
            <p class="wp-muted">{{ __('settings.org.readonly_hint') }}</p>
            @if (auth()->user()->tenant?->name)
                <p><strong>{{ auth()->user()->tenant->name }}</strong></p>
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
                        <label class="wp-label" for="orgName">{{ __('settings.org.name_label') }}</label>
                        <input type="text" id="orgName" class="wp-input" wire:model="orgName" autocomplete="organization">
                        @error('orgName') <p class="wp-error">{{ $message }}</p> @enderror
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
