@props(['kpis', 'statusFilter'])

@php
    use App\Enums\TimePresenceStatusFilter;

    $tiles = [
        ['key' => 'active', 'value' => $kpis->active, 'filter' => TimePresenceStatusFilter::Active, 'icon' => 'team', 'label' => 'time.presence.kpi.active'],
        ['key' => 'break', 'value' => $kpis->onBreak, 'filter' => TimePresenceStatusFilter::Break, 'icon' => 'hourglass', 'label' => 'time.presence.kpi.break'],
        ['key' => 'absent', 'value' => $kpis->notClockedIn, 'filter' => TimePresenceStatusFilter::Absent, 'icon' => 'team', 'label' => 'time.presence.kpi.absent'],
    ];
@endphp

<div class="wp-kpis wp-time-presence-kpis">
    <button type="button"
            wire:click="setStatusFilter('all')"
            @class([
                'wp-kpi wp-kpi--present_now',
                'wp-kpi--selected' => $statusFilter === TimePresenceStatusFilter::All,
            ])>
        <div class="wp-kpi-body">
            <div class="wp-kpi-main">
                <p class="wp-kpi-kicker">{{ __('time.presence.kpi.clocked_in') }}</p>
                <p class="wp-kpi-stats">
                    <span class="wp-kpi-value wp-tabular">{{ $kpis->clockedIn }}</span>
                </p>
            </div>
            <span class="wp-kpi-icon" aria-hidden="true">
                <x-wp-icon name="clock" />
            </span>
        </div>
    </button>

    @foreach ($tiles as $tile)
        <button type="button"
                wire:click="setStatusFilter('{{ $tile['filter']->value }}')"
                @class([
                    'wp-kpi',
                    'wp-kpi--'.$tile['key'],
                    'wp-kpi--selected' => $statusFilter === $tile['filter'],
                ])>
            <div class="wp-kpi-body">
                <div class="wp-kpi-main">
                    <p class="wp-kpi-kicker">{{ __($tile['label']) }}</p>
                    <p class="wp-kpi-stats">
                        <span class="wp-kpi-value wp-tabular">{{ $tile['value'] }}</span>
                    </p>
                </div>
                <span class="wp-kpi-icon" aria-hidden="true">
                    <x-wp-icon :name="$tile['icon']" />
                </span>
            </div>
        </button>
    @endforeach

    <a href="{{ route('time.alarms.index') }}"
       @class([
           'wp-kpi wp-kpi--attention',
           'wp-kpi--alert' => $kpis->attention > 0,
       ])>
        <div class="wp-kpi-body">
            <div class="wp-kpi-main">
                <p class="wp-kpi-kicker">{{ __('time.presence.kpi.attention') }}</p>
                <p class="wp-kpi-stats">
                    <span class="wp-kpi-value wp-tabular">{{ $kpis->attention }}</span>
                </p>
            </div>
            <span class="wp-kpi-icon" aria-hidden="true">
                <x-wp-icon name="alert-triangle" />
            </span>
        </div>
    </a>
</div>
