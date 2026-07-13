@props(['statusFilter'])

@php
    use App\Enums\TimePresenceStatusFilter;

    $filters = [
        TimePresenceStatusFilter::All,
        TimePresenceStatusFilter::Active,
        TimePresenceStatusFilter::Break,
        TimePresenceStatusFilter::Absent,
    ];
@endphp

<div class="wp-cluster wp-cluster--tight wp-time-presence-status-filters" role="tablist" aria-label="{{ __('time.presence.status_filters') }}">
    @foreach ($filters as $filter)
        <button type="button"
                role="tab"
                wire:click="setStatusFilter('{{ $filter->value }}')"
                @class([
                    'btn btn--sm',
                    $statusFilter === $filter ? 'btn--primary' : 'btn--surface',
                ])>
            {{ __('time.presence.status.'.$filter->value) }}
        </button>
    @endforeach
    <a href="{{ route('time.alarms.index') }}" class="btn btn--sm btn--surface">
        {{ __('time.presence.status.attention') }}
    </a>
</div>
