@props(['viewMode'])

@php
    use App\Enums\TimePresenceViewMode;
@endphp

<div class="wp-cluster wp-cluster--tight wp-time-presence-view-toggle" role="tablist" aria-label="{{ __('time.presence.view_modes') }}">
    <button type="button"
            role="tab"
            wire:click="setViewMode('teams')"
            @class(['btn btn--sm', $viewMode === TimePresenceViewMode::Teams ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.presence.view.teams') }}
    </button>
    <button type="button"
            role="tab"
            wire:click="setViewMode('cards')"
            @class(['btn btn--sm', $viewMode === TimePresenceViewMode::Cards ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.presence.view.cards') }}
    </button>
    <button type="button"
            role="tab"
            wire:click="setViewMode('locations')"
            @class(['btn btn--sm', $viewMode === TimePresenceViewMode::Locations ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.presence.view.locations') }}
    </button>
</div>
