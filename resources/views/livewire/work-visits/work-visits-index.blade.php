<div class="wp-stack" data-manual-capture="work-visits-index">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="map-pin"
                :title="__('work_visits.list.title')"
                help-page="work-visits.index"
                :subtitle="__('work_visits.list.subtitle')"
            />
        </div>
    </div>

    <div class="wp-card wp-filter-panel wp-work-visits-toolbar">
        <div class="wp-filter-form wp-work-visits-toolbar__form">
            <p class="wp-filter-form__title">{{ __('common.list.filters_title') }}</p>
            <div class="wp-filter-cell">
                <label class="wp-filter-inline-label" for="work-visits-from">{{ __('time.filters.from') }}</label>
                <input id="work-visits-from" type="date" class="wp-input" wire:model="from">
            </div>
            <div class="wp-filter-cell">
                <label class="wp-filter-inline-label" for="work-visits-to">{{ __('time.filters.to') }}</label>
                <input id="work-visits-to" type="date" class="wp-input" wire:model="to">
            </div>
            <div class="wp-filter-cell">
                <label class="wp-filter-inline-label" for="work-visits-worker">{{ __('time.filters.worker') }}</label>
                <select id="work-visits-worker" class="wp-select" wire:model="workerFilter">
                    <option value="">{{ __('time.filters.all_workers') }}</option>
                    @foreach ($workers as $worker)
                        <option value="{{ $worker->id }}">{{ $worker->displayName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="wp-filter-cell">
                <label class="wp-filter-inline-label" for="work-visits-location">{{ __('time.filters.location') }}</label>
                <select id="work-visits-location" class="wp-select" wire:model="locationFilter">
                    <option value="">{{ __('time.filters.all_locations') }}</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name ?: $location->address }}</option>
                    @endforeach
                </select>
            </div>
            <div class="wp-filter-cell">
                <label class="wp-filter-inline-label" for="work-visits-status">{{ __('work_visits.filter.status') }}</label>
                <select id="work-visits-status" class="wp-select" wire:model="statusFilter">
                    <option value="">{{ __('work_visits.filter.all_statuses') }}</option>
                    <option value="open">{{ __('work_visits.status.open') }}</option>
                    <option value="closed">{{ __('work_visits.status.closed') }}</option>
                </select>
            </div>
            <button type="button" class="btn btn--primary btn--sm" wire:click="applyFilters">{{ __('time.filters.apply') }}</button>
        </div>
    </div>

    <div class="wp-list">
        @forelse ($visitDays as $day)
            <div class="wp-card wp-card-pad" wire:key="work-visit-day-{{ $day['worker_id'] }}-{{ $day['date'] }}">
                <div class="wp-stack-tight">
                    <div class="wp-cluster wp-cluster--wrap">
                        <strong>{{ $day['worker']?->displayName() }}</strong>
                        <span class="wp-pill {{ $day['has_open'] ? 'wp-pill--progress' : 'wp-pill--done' }}">
                            {{ $day['has_open'] ? __('work_visits.status.open') : __('work_visits.status.closed') }}
                        </span>
                    </div>
                    <p class="wp-muted wp-text-sm">
                        {{ \Illuminate\Support\Carbon::parse($day['date'])->format('d-m-Y') }}
                        &middot; {{ __('work_visits.list.duration', ['duration' => \App\Support\Time\WorkDurationFormatter::format($day['total_minutes'])]) }}
                    </p>
                    @foreach ($day['customers'] as $customer)
                        <div class="wp-stack-tight" wire:key="work-visit-day-{{ $day['worker_id'] }}-{{ $day['date'] }}-loc-{{ $customer['location_id'] ?? 'none' }}">
                            <p class="wp-muted wp-text-sm">
                                <strong>{{ $customer['name'] }}</strong>
                                &middot; {{ __('work_visits.list.duration', ['duration' => \App\Support\Time\WorkDurationFormatter::format($customer['minutes'])]) }}
                            </p>
                            <ul class="wp-muted wp-text-sm">
                                @foreach ($customer['visits'] as $visit)
                                    @php
                                        $visitPlace = $visit->unit?->localizedName()
                                            ?: ($visit->location?->localizedName() ?: ($visit->location?->name ?? '—'));
                                        $visitStart = $visit->started_at?->format('H:i') ?? '—';
                                    @endphp
                                    <li wire:key="work-visit-{{ $visit->id }}">
                                        @if ($visit->ended_at)
                                            {{ __('time.shifts.visit_range', ['start' => $visitStart, 'end' => $visit->ended_at->format('H:i'), 'place' => $visitPlace]) }}
                                        @else
                                            {{ __('time.shifts.visit_open', ['start' => $visitStart, 'place' => $visitPlace]) }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="wp-muted">{{ __('work_visits.list.empty') }}</p>
        @endforelse
    </div>

    @if ($visitDays->hasPages())
        <div class="wp-pagination">
            {{ $visitDays->links() }}
        </div>
    @endif
</div>
