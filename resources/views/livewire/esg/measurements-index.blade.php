@php
    use App\Support\Esg\EsgMeasurementPresenter;
@endphp

<div class="wp-stack" data-manual-capture="esg-measurements">
    <x-wp-page-head-title
        icon="sliders"
        :title="__('esg.title')"
        help-page="esg.measurements"
        :subtitle="__('esg.measurements.subtitle')"
    />

    @include('partials.wp-esg-subnav')

    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-section-title">{{ __('esg.measurements.list_title') }}</p>

        <form wire:submit="applyFilters" class="wp-stack-tight">
            <div class="wp-cluster wp-cluster--wrap">
                <div class="wp-field wp-field--grow">
                    <label class="wp-label" for="esg-filter-indicator">{{ __('esg.measurements.filters.indicator') }}</label>
                    <select id="esg-filter-indicator" class="wp-input" wire:model="indicatorFilter">
                        <option value="">{{ __('esg.measurements.filters.all_indicators') }}</option>
                        @foreach ($indicators as $indicator)
                            <option value="{{ $indicator->id }}">{{ $indicator->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-field wp-field--grow">
                    <label class="wp-label" for="esg-filter-location">{{ __('esg.measurements.filters.location') }}</label>
                    <select id="esg-filter-location" class="wp-input" wire:model.live="locationFilter">
                        <option value="">{{ __('esg.measurements.filters.all_locations') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-field wp-field--grow">
                    <label class="wp-label" for="esg-filter-unit">{{ __('esg.measurements.filters.unit') }}</label>
                    <select id="esg-filter-unit" class="wp-input" wire:model="unitFilter">
                        <option value="">{{ __('esg.measurements.filters.all_units') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="wp-cluster wp-cluster--wrap">
                <div class="wp-field wp-field--grow">
                    <label class="wp-label" for="esg-filter-from">{{ __('esg.measurements.filters.from') }}</label>
                    <input id="esg-filter-from" type="date" class="wp-input" wire:model="recordedFrom">
                </div>
                <div class="wp-field wp-field--grow">
                    <label class="wp-label" for="esg-filter-to">{{ __('esg.measurements.filters.to') }}</label>
                    <input id="esg-filter-to" type="date" class="wp-input" wire:model="recordedTo">
                </div>
            </div>
            <div class="wp-cluster wp-cluster--end">
                <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">
                    {{ __('esg.measurements.filters.reset') }}
                </button>
                <button type="submit" class="btn btn--primary btn--sm">
                    {{ __('esg.measurements.filters.apply') }}
                </button>
            </div>
        </form>
    </div>

    <div class="wp-card wp-card-pad wp-stack-tight">
        @if ($measurements->isEmpty())
            <p class="wp-muted">
                {{ $showSetupSteps ? __('esg.measurements.empty') : __('esg.measurements.empty_filtered') }}
            </p>
            @if ($showSetupSteps)
                @include('partials.wp-esg-setup-steps', ['setupKey' => 'esg.measurements.setup'])
            @endif
        @else
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($measurements as $measurement)
                    @php($outsideThresholds = EsgMeasurementPresenter::isOutsideThresholds($measurement))
                    <li class="wp-list-row" wire:key="esg-measurement-{{ $measurement->id }}">
                        <div>
                            <strong>{{ $measurement->indicator?->name ?? '—' }}</strong>
                            <p class="wp-muted wp-text-sm">
                                {{ EsgMeasurementPresenter::displayValue($measurement) }}
                            </p>
                            <p class="wp-muted wp-text-sm">
                                {{ $measurement->location?->name ?? '—' }}
                                @if ($measurement->unit)
                                    · {{ $measurement->unit->name }}
                                @endif
                                · {{ optional($measurement->recorded_at)->format('d-m-Y H:i') }}
                            </p>
                            @if ($measurement->worker)
                                <p class="wp-muted wp-text-sm">
                                    {{ trim($measurement->worker->first_name.' '.$measurement->worker->last_name) }}
                                </p>
                            @endif
                        </div>
                        <div class="wp-cluster">
                            @if ($measurement->corrects_measurement_id)
                                <span class="wp-pill wp-pill--closed">{{ __('esg.measurements.correction') }}</span>
                            @endif
                            @if ($outsideThresholds)
                                <span class="wp-pill wp-pill--progress">{{ __('esg.measurements.outside_thresholds') }}</span>
                            @endif
                            @if ($measurement->task_id)
                                <a href="{{ route('tasks.show', $measurement->task_id) }}" class="btn btn--ghost btn--sm">
                                    {{ __('esg.measurements.view_task') }}
                                </a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            {{ $measurements->links() }}
        @endif
    </div>
</div>
