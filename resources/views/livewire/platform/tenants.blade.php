<div class="wp-stack">
    <div class="wp-stack-tight">
        <h1 class="wp-page-title">{{ __('platform.title') }}</h1>
        <p class="wp-muted">{{ __('platform.subtitle') }}</p>
    </div>

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
                        <button type="button" class="btn btn--primary btn--sm"
                                wire:click="startSupport({{ $tenant->id }})">
                            {{ __('platform.open_support') }}
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
