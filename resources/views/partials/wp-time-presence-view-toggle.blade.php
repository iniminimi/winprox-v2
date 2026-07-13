@php
    use App\Enums\TimePresenceViewMode;
@endphp

<div class="wp-cluster wp-cluster--tight wp-time-presence-view-toggle" role="tablist" aria-label="{{ __('time.presence.view_modes') }}">
    <button type="button"
            role="tab"
            wire:click="setViewMode('teams')"
            aria-selected="{{ $presenceView === TimePresenceViewMode::Teams ? 'true' : 'false' }}"
            @class(['btn btn--sm', $presenceView === TimePresenceViewMode::Teams ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.presence.view.teams') }}
    </button>
    <button type="button"
            role="tab"
            wire:click="setViewMode('cards')"
            aria-selected="{{ $presenceView === TimePresenceViewMode::Cards ? 'true' : 'false' }}"
            @class(['btn btn--sm', $presenceView === TimePresenceViewMode::Cards ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.presence.view.cards') }}
    </button>
    <button type="button"
            role="tab"
            wire:click="setViewMode('locations')"
            aria-selected="{{ $presenceView === TimePresenceViewMode::Locations ? 'true' : 'false' }}"
            @class(['btn btn--sm', $presenceView === TimePresenceViewMode::Locations ? 'btn--primary' : 'btn--surface'])>
        {{ __('time.presence.view.locations') }}
    </button>
</div>
