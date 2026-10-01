<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="building-office"
                help-page="customers.stats"
                :title="__('customers.stats.title')"
                :subtitle="__('customers.stats.subtitle', ['customer' => $customer->name])"
            />
        </div>
        <div class="wp-cluster">
            <a href="{{ route('customers.index') }}" class="btn btn--ghost btn--sm">
                {{ __('customers.stats.back_to_customers') }}
            </a>
            <x-wp-list-export :csv-url="$exportUrl" :print-url="$printUrl" />
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-cluster">
            <button
                type="button"
                class="wp-pagination__control"
                wire:click="previousMonth"
                aria-label="{{ __('time.schedule.prev_month') }}"
            >{{ __('time.schedule.nav_prev') }}</button>
            <p class="wp-data-row-title">{{ $from->translatedFormat('F Y') }}</p>
            <button
                type="button"
                class="wp-pagination__control"
                wire:click="nextMonth"
                aria-label="{{ __('time.schedule.next_month') }}"
            >{{ __('time.schedule.nav_next') }}</button>
        </div>

        <div class="wp-cluster">
            <div>
                <p class="wp-muted">{{ __('customers.stats.worked_time') }}</p>
                <p class="wp-section-title">{{ \App\Support\Time\WorkDurationFormatter::format($stats->minutes) }}</p>
            </div>
            <div>
                <p class="wp-muted">{{ __('customers.stats.visits_label') }}</p>
                <p class="wp-section-title">{{ trans_choice('customers.stats.visits', $stats->visits, ['count' => $stats->visits]) }}</p>
            </div>
            <div>
                <p class="wp-muted">{{ __('customers.stats.locations_label') }}</p>
                <p class="wp-section-title">{{ trans_choice('customers.locations_count', $stats->visitedLocations(), ['count' => $stats->visitedLocations()]) }}</p>
            </div>
        </div>

        <p class="wp-hint">{{ __('customers.stats.presence_note') }}</p>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('customers.stats.per_location') }}</h2>

        <div class="wp-list wp-list--entity-rows">
            @forelse ($locationRows as $row)
                <div class="wp-data-row" wire:key="stats-location-{{ $row['stats']->locationId }}">
                    <div class="wp-data-row-main">
                        <span class="wp-data-row-title">{{ $row['location']?->name ?: '—' }}</span>
                        <span class="wp-muted">
                            {{ $row['location']?->formattedAddress() }}
                        </span>
                    </div>
                    <div class="wp-cluster wp-cluster--tight">
                        <span class="wp-muted">
                            {{ \App\Support\Time\WorkDurationFormatter::format($row['stats']->minutes) }}
                            · {{ trans_choice('customers.stats.visits', $row['stats']->visits, ['count' => $row['stats']->visits]) }}
                        </span>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('customers.stats.empty') }}</p>
            @endforelse
        </div>
    </div>
</div>
