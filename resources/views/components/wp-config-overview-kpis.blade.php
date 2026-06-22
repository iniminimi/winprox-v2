@props([
    'summary',
])

@php
    assert($summary instanceof \App\Support\Admin\AdminConfigSummary);
@endphp

<div {{ $attributes->merge(['class' => 'wp-config-overview-kpis']) }}>
    <div class="wp-config-overview-kpi">
        <p class="wp-config-overview-kpi__label">{{ __('settings.config_overview.kpi.inactive_locations') }}</p>
        <p class="wp-config-overview-kpi__value wp-tabular">{{ $summary->inactiveLocationCount }}</p>
    </div>
    <div class="wp-config-overview-kpi">
        <p class="wp-config-overview-kpi__label">{{ __('settings.config_overview.kpi.inactive_units') }}</p>
        <p class="wp-config-overview-kpi__value wp-tabular">{{ $summary->inactiveUnitCount }}</p>
    </div>
    <div class="wp-config-overview-kpi">
        <p class="wp-config-overview-kpi__label">{{ __('settings.config_overview.kpi.inactive_teams') }}</p>
        <p class="wp-config-overview-kpi__value wp-tabular">{{ $summary->inactiveTeamCount }}</p>
    </div>
    <div class="wp-config-overview-kpi">
        <p class="wp-config-overview-kpi__label">{{ __('settings.config_overview.kpi.inactive_workers') }}</p>
        <p class="wp-config-overview-kpi__value wp-tabular">{{ $summary->inactiveWorkerCount }}</p>
    </div>
    <div class="wp-config-overview-kpi">
        <p class="wp-config-overview-kpi__label">{{ __('settings.config_overview.kpi.category_gps_on') }}</p>
        <p class="wp-config-overview-kpi__value wp-tabular">{{ $summary->categoryGpsEnabledCount }}</p>
    </div>
    <div class="wp-config-overview-kpi">
        <p class="wp-config-overview-kpi__label">{{ __('settings.config_overview.kpi.category_gps_off') }}</p>
        <p class="wp-config-overview-kpi__value wp-tabular">{{ $summary->categoryGpsDisabledCount }}</p>
    </div>
    <div class="wp-config-overview-kpi">
        <p class="wp-config-overview-kpi__label">{{ __('settings.config_overview.kpi.documents_active') }}</p>
        <p class="wp-config-overview-kpi__value wp-tabular">{{ $summary->activeDocumentCount }}</p>
    </div>
    <div class="wp-config-overview-kpi">
        <p class="wp-config-overview-kpi__label">{{ __('settings.config_overview.kpi.documents_inactive') }}</p>
        <p class="wp-config-overview-kpi__value wp-tabular">{{ $summary->inactiveDocumentCount }}</p>
    </div>
</div>
