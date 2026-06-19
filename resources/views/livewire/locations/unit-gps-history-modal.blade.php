<div>
    @if ($show && $unit)
        <x-wp-modal closeMethod="close">
        <div class="wp-card wp-card-pad wp-stack wp-modal-card" data-manual-capture="locations-gps-history">
            <div class="wp-modal-head">
                <h2 class="wp-section-title">{{ __('locations.units.gps_history.title', ['unit' => $unit->localizedName()]) }}</h2>
                <x-wp-modal-close wire:click="close" />
            </div>

            @if ($reports->isNotEmpty())
                <div class="wp-list wp-list--entity-rows">
                    @foreach ($reports as $report)
                        <div class="wp-issue-row" wire:key="gps-report-{{ $report->id }}">
                            <div class="wp-grow">
                                <p class="wp-issue-card-title">
                                    {{ $report->latitude }}, {{ $report->longitude }}
                                    @if ($report->location_name && $report->country_code)
                                        · {{ __('locations.units.gps_history.location_named', ['name' => $report->location_name, 'country' => $report->country_code]) }}
                                    @elseif ($report->country_code)
                                        · {{ __('locations.units.gps_history.location_country_only', ['country' => $report->country_code]) }}
                                    @endif
                                </p>
                                <p class="wp-muted">
                                    {{ __('locations.units.gps_history.reported_at', ['datetime' => $report->reported_at->format('d/m/Y H:i')]) }}
                                    @if ($report->worker)
                                        · {{ __('locations.units.gps_history.worker', ['name' => $report->worker->displayName()]) }}
                                    @endif
                                </p>
                            </div>
                            <div class="wp-cluster">
                                <a href="{{ $report->googleMapsUrl() }}" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">
                                    {{ __('locations.units.gps_history.open_maps') }}
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="wp-muted">{{ __('locations.units.gps_history.empty') }}</p>
            @endif
        </div>
        </x-wp-modal>
    @endif
</div>
