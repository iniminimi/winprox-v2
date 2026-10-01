@php
    $organisationLogoUrl = $tenant->logoPublicUrl();
    $organisationAddress = $tenant->organisationAddressLine();
@endphp

<x-layouts.print :title="__('customers.stats.print.document_title', ['customer' => $customer->name])">
    <div class="wp-container wp-stack">
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="building-office"
                    :title="__('customers.stats.print.title', ['customer' => $customer->name])"
                />
                <p class="wp-muted">{{ __('customers.stats.period', ['period' => $from->translatedFormat('F Y')]) }}</p>
                <div class="wp-cluster wp-no-print">
                    <button type="button" class="btn btn--primary btn--sm" onclick="window.print()">{{ __('common.button.print') }}</button>
                </div>
            </div>
            <div class="wp-cluster wp-cluster--tight wp-page-actions">
                <div class="wp-sidebar-header-logo">
                    <img
                        src="{{ $organisationLogoUrl ?? asset('images/Winprox_logo_100.png') }}"
                        alt="{{ $tenant->name }}"
                    >
                </div>
                <p class="wp-muted">
                    <strong class="wp-text-body">{{ $tenant->name }}</strong>
                    @if ($organisationAddress)
                        <br>{{ $organisationAddress }}
                    @endif
                </p>
            </div>
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-cluster">
                <div>
                    <p class="wp-muted">{{ __('customers.stats.worked_time') }}</p>
                    <p class="wp-section-title">{{ \App\Support\Time\WorkDurationFormatter::format($stats?->minutes ?? 0) }}</p>
                </div>
                <div>
                    <p class="wp-muted">{{ __('customers.stats.visits_label') }}</p>
                    <p class="wp-section-title">{{ trans_choice('customers.stats.visits', $stats?->visits ?? 0, ['count' => $stats?->visits ?? 0]) }}</p>
                </div>
                <div>
                    <p class="wp-muted">{{ __('customers.stats.locations_label') }}</p>
                    <p class="wp-section-title">{{ trans_choice('customers.locations_count', $stats?->visitedLocations() ?? 0, ['count' => $stats?->visitedLocations() ?? 0]) }}</p>
                </div>
            </div>
            <p class="wp-hint">{{ __('customers.stats.presence_note') }}</p>
        </div>

        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('customers.stats.per_location') }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('customers.stats.export_columns.location') }}</th>
                        <th>{{ __('customers.stats.export_columns.worked') }}</th>
                        <th>{{ __('customers.stats.export_columns.visits') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (collect($stats?->locations ?? [])->sortByDesc('minutes') as $row)
                        @php($location = $locations->get($row->locationId))
                        <tr>
                            <td>{{ $location?->name ?: '—' }}@if ($location?->formattedAddress()) — {{ $location->formattedAddress() }}@endif</td>
                            <td>{{ \App\Support\Time\WorkDurationFormatter::format($row->minutes) }}</td>
                            <td>{{ $row->visits }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="wp-muted">{{ __('customers.stats.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.print>
