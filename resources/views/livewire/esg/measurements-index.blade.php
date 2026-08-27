@php
    use App\Support\Esg\EsgMeasurementPresenter;
    use App\Support\Esg\EsgOperationChainPresenter;
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
            <div class="wp-filter-grid wp-filter-grid--3">
                <div class="wp-filter-field">
                    <label class="wp-label" for="esg-filter-indicator">{{ __('esg.measurements.filters.indicator') }}</label>
                    <select id="esg-filter-indicator" class="wp-input" wire:model="indicatorFilter">
                        <option value="">{{ __('esg.measurements.filters.all_indicators') }}</option>
                        @foreach ($indicators as $indicator)
                            <option value="{{ $indicator->id }}">{{ $indicator->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-filter-field">
                    <label class="wp-label" for="esg-filter-location">{{ __('esg.measurements.filters.location') }}</label>
                    <select id="esg-filter-location" class="wp-input" wire:model.live="locationFilter">
                        <option value="">{{ __('esg.measurements.filters.all_locations') }}</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}">{{ $location->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wp-filter-field">
                    <label class="wp-label" for="esg-filter-unit">{{ __('esg.measurements.filters.unit') }}</label>
                    <select id="esg-filter-unit" class="wp-input" wire:model="unitFilter" @disabled($units->isEmpty())>
                        <option value="">{{ __('esg.measurements.filters.all_units') }}</option>
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->localizedName() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="wp-filter-grid">
                <div class="wp-filter-field">
                    <label class="wp-label" for="esg-filter-from">{{ __('esg.measurements.filters.from') }}</label>
                    <x-wp-date-input id="esg-filter-from" wire:model="recordedFrom" />
                </div>
                <div class="wp-filter-field">
                    <label class="wp-label" for="esg-filter-to">{{ __('esg.measurements.filters.to') }}</label>
                    <x-wp-date-input id="esg-filter-to" wire:model="recordedTo" />
                </div>
                <div class="wp-filter-field wp-filter-field--checkbox">
                    <label class="wp-checkbox">
                        <input type="checkbox" wire:model="alarmsOnly">
                        <span>{{ __('esg.measurements.filters.alarms_only') }}</span>
                    </label>
                </div>
            </div>
            <div class="wp-cluster wp-cluster--end">
                <button type="button" class="btn btn--ghost btn--sm" wire:click="resetFilters">
                    {{ __('esg.measurements.filters.reset') }}
                </button>
                <x-wp-list-export :csv-url="$exportUrl" :print-url="$printUrl" />
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
                            <strong>{{ $measurement->indicator?->localizedName() ?? '—' }}</strong>
                            <p class="wp-muted wp-text-sm">
                                {{ EsgMeasurementPresenter::displayValue($measurement) }}
                            </p>
                            @if ($measurement->corrects_measurement_id && $measurement->correctsMeasurement)
                                <p class="wp-muted wp-text-sm">
                                    {{ __('esg.measurements.corrects_original', [
                                        'value' => EsgMeasurementPresenter::displayValue($measurement->correctsMeasurement),
                                    ]) }}
                                </p>
                            @endif
                            <p class="wp-muted wp-text-sm">
                                @if ($measurement->location)
                                    <a href="{{ route('locations.show', $measurement->location) }}">{{ $measurement->location->localizedName() }}</a>
                                @else
                                    —
                                @endif
                                @if ($measurement->unit)
                                    · <a href="{{ route('esg.point.history', ['unit' => $measurement->unit_id, 'indicator' => $measurement->esg_indicator_id]) }}">{{ $measurement->unit->localizedName() }}</a>
                                @endif
                                · {{ optional($measurement->recorded_at)->format('d-m-Y H:i') }}
                            </p>
                            @include('partials.wp-esg-operation-chain', [
                                'steps' => EsgOperationChainPresenter::stepsForMeasurement($measurement),
                            ])
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
                            @if ($measurement->thresholdFollowUpTask)
                                <a href="{{ route('tasks.show', $measurement->thresholdFollowUpTask) }}" class="btn btn--primary btn--sm">
                                    {{ __('esg.dashboard.auto_task.follow_up') }}
                                </a>
                            @endif
                            @can('correct', $measurement)
                                <button type="button"
                                        class="btn btn--ghost btn--sm"
                                        wire:click="openCorrectionModal({{ $measurement->id }})">
                                    {{ __('esg.measurements.correct_action') }}
                                </button>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>

            {{ $measurements->links() }}
        @endif
    </div>

    @if ($showCorrectionModal && $correctingMeasurement?->indicator)
        <x-wp-modal closeMethod="closeCorrectionModal">
            <div class="wp-modal__panel wp-card wp-card-pad wp-stack-tight">
                <h2 class="wp-section-title">{{ __('esg.measurements.correction_modal.title') }}</h2>
                <p class="wp-muted">{{ $correctingMeasurement->indicator->localizedName() }}</p>
                <p class="wp-muted wp-text-sm">
                    {{ __('esg.measurements.correction_modal.original_value', [
                        'value' => EsgMeasurementPresenter::displayValue($correctingMeasurement),
                    ]) }}
                </p>

                @include('partials.wp-esg-correction-value-fields', [
                    'indicator' => $correctingMeasurement->indicator,
                ])

                <div class="wp-cluster wp-cluster--end">
                    <button type="button" class="btn btn--ghost" wire:click="closeCorrectionModal">
                        {{ __('common.button.cancel') }}
                    </button>
                    <button type="button" class="btn btn--primary" wire:click="saveCorrection">
                        {{ __('common.button.save') }}
                    </button>
                </div>
            </div>
        </x-wp-modal>
    @endif
</div>
