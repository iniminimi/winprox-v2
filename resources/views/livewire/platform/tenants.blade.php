<div class="wp-stack">
    <x-wp-page-head-title
        icon="subscription"
        :title="__('platform.title')"
        help-page="platform.tenants"
        :subtitle="__('platform.subtitle')"
    />

    <x-wp-translation-sync-reminder />

    @if ($activeTenant)
        <div class="wp-card wp-card-pad wp-support-banner">
            <p>{{ __('platform.active', ['name' => $activeTenant->name]) }}</p>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="stopSupport">
                {{ __('platform.stop') }}
            </button>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <label class="wp-label" for="platform-search">{{ __('platform.search') }}</label>
        <input id="platform-search" type="search" class="wp-input" wire:model.live.debounce.300ms="search"
               placeholder="{{ __('platform.search_placeholder') }}" autocomplete="off">

        @if ($tenants->isEmpty())
            <p class="wp-muted">{{ __('platform.empty') }}</p>
        @else
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($tenants as $tenant)
                    <li class="wp-list-row">
                        <div>
                            <strong>{{ $tenant->name }}</strong>
                            <p class="wp-muted wp-text-sm">
                                #{{ $tenant->id }}
                                · {{ $tenant->is_active ? __('platform.status_active') : __('platform.status_inactive') }}
                            </p>
                        </div>
                        <div class="wp-cluster">
                            @if ($tenant->isTrialActive())
                                <label class="wp-chip wp-chip--sm">
                                    <input type="checkbox" wire:click="toggleTrialApi({{ $tenant->id }})" {{ $tenant->allow_trial_api ? 'checked' : '' }}>
                                    <span>{{ __('platform.trial_api') }}</span>
                                </label>
                            @endif
                            <label class="wp-chip wp-chip--sm">
                                <input type="checkbox" wire:click="toggleEsgModule({{ $tenant->id }})" {{ $tenant->has_esg_module ? 'checked' : '' }}>
                                <span>{{ __('platform.esg_module') }}</span>
                            </label>
                            <label class="wp-chip wp-chip--sm">
                                <input type="checkbox" wire:click="toggleTimeModule({{ $tenant->id }})" {{ $tenant->has_time_module ? 'checked' : '' }}>
                                <span>{{ __('platform.time_module') }}</span>
                            </label>
                            <button type="button" class="btn btn--primary btn--sm"
                                    wire:click="startSupport({{ $tenant->id }})">
                                {{ __('platform.open_support') }}
                            </button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
