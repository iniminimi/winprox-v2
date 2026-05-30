@php use Illuminate\Support\Facades\Storage; @endphp
<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('settings.title') }}</h1>
        <p class="wp-muted">{{ __('settings.subtitle') }}</p>
    </div>

    <form wire:submit="saveOrganisation" class="wp-card wp-card-pad wp-stack-tight">
        <h2 class="wp-section-title">{{ __('settings.org.title') }}</h2>
        <div class="wp-field">
            <label class="wp-label" for="orgName">{{ __('settings.org.name_label') }}</label>
            <input type="text" id="orgName" class="wp-input" wire:model="orgName">
            @error('orgName') <p class="wp-error">{{ $message }}</p> @enderror
        </div>
        <div class="wp-field">
            <label class="wp-label" for="orgLogo">{{ __('settings.org.logo_label') }}</label>
            @php
                $settingsTenant = auth()->user()->tenant;
                if (! $settingsTenant && auth()->user()->is_superuser && \App\Support\Platform\SupportTenantContext::isActive()) {
                    $settingsTenant = \App\Models\Tenant::query()->find(\App\Support\Platform\SupportTenantContext::activeTenantId());
                }
            @endphp
            @if ($settingsTenant?->logo_path)
                <p class="wp-hint">{{ __('settings.org.logo_current') }}</p>
                <img src="{{ Storage::disk('public')->url($settingsTenant->logo_path) }}" alt="" class="wp-org-logo-preview" width="80" height="80">
            @endif
            <input type="file" id="orgLogo" class="wp-input" wire:model="orgLogo" accept="image/*">
            @error('orgLogo') <p class="wp-error">{{ $message }}</p> @enderror
            <p class="wp-hint">{{ __('settings.org.logo_hint') }}</p>
        </div>
        <div class="wp-cluster">
            <button type="submit" class="btn btn--primary btn--sm">{{ __('common.button.save') }}</button>
        </div>
    </form>

    <div class="wp-card wp-card-pad wp-stack-tight">
        <h2 class="wp-section-title">{{ __('settings.privacy.title') }}</h2>
        <p class="wp-muted">{{ __('settings.privacy.hint') }}</p>
        <p>
            <a href="{{ route('account.data-export') }}" class="btn btn--ghost btn--sm">{{ __('settings.privacy.download') }}</a>
        </p>
    </div>

    <div class="wp-card wp-card-pad wp-stack-tight">
        <h2 class="wp-section-title">{{ __('settings.style.title') }}</h2>
        <p class="wp-muted">{{ __('settings.style.coming_soon') }}</p>
    </div>
</div>
