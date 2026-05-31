<div class="wp-stack" x-data x-on:ui-theme-changed.window="document.documentElement.dataset.theme = $event.detail.theme">
    <x-wp-page-head-title
        icon="settings"
        :title="__('settings.title')"
        help-page="settings"
        :subtitle="__('settings.subtitle')"
    />

    @if ($canManageOrganisation)
        <form wire:submit="saveOrganisation" class="wp-card wp-card-pad wp-stack-tight">
            <h2 class="wp-section-title">{{ __('settings.org.title') }}</h2>
            <div class="wp-field">
                <label class="wp-label" for="orgName">{{ __('settings.org.name_label') }}</label>
                <input type="text" id="orgName" class="wp-input" wire:model="orgName">
                @error('orgName') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-field">
                <label class="wp-label" for="orgLogo">{{ __('settings.org.logo_label') }}</label>
                @if ($organisationLogoUrl)
                    <div class="wp-org-logo-preview-wrap">
                        <p class="wp-hint">{{ __('settings.org.logo_current') }}</p>
                        <img
                            src="{{ $organisationLogoUrl }}"
                            alt=""
                            class="wp-org-logo-preview"
                            width="120"
                            height="120"
                            wire:key="org-logo-preview-{{ md5($organisationLogoUrl) }}"
                        >
                    </div>
                @endif
                <input type="file" id="orgLogo" class="wp-input" wire:model="orgLogo" accept="image/*">
                @error('orgLogo') <p class="wp-error">{{ $message }}</p> @enderror
                <p class="wp-hint">{{ __('settings.org.logo_hint') }}</p>
            </div>
            <div class="wp-cluster">
                <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
            </div>
        </form>
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
</div>
