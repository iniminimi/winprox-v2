@php
    use App\Support\Esg\EsgMeasurementPresenter;
    use App\Support\Esg\EsgOperationChainPresenter;
@endphp

<div class="wp-stack" data-manual-capture="esg-point-history">
    <x-wp-page-head-title
        icon="sliders"
        :title="__('esg.point.title')"
        help-page="esg.point"
        :subtitle="__('esg.point.subtitle', [
            'unit' => $history->unit->localizedName(),
            'location' => $history->locationName,
        ])"
    />

    @include('partials.wp-esg-subnav')

    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-row">
            <div class="wp-stack-tight wp-grow">
                <p class="wp-section-title">{{ $history->unit->localizedName() }}</p>
                <p class="wp-muted wp-text-sm">{{ $history->locationName }}</p>
            </div>
            <a href="{{ route('locations.show', $history->unit->location_id) }}" class="btn btn--ghost btn--sm">
                {{ __('esg.point.location') }}
            </a>
        </div>

        @if ($history->indicatorOptions !== [])
            <div class="wp-filter-field wp-esg-trend__select">
                <label class="wp-label" for="esg-point-indicator">{{ __('esg.point.indicator') }}</label>
                <select id="esg-point-indicator" class="wp-input" wire:model.live="indicatorId">
                    @foreach ($history->indicatorOptions as $option)
                        <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="wp-cluster">
            <span class="wp-pill wp-pill--closed wp-tabular">
                {{ trans_choice('esg.point.readings', $history->measurementCount, ['count' => $history->measurementCount]) }}
            </span>
            @if ($history->alarmCount > 0)
                <span class="wp-pill wp-pill--progress wp-tabular">
                    {{ trans_choice('esg.point.alarms', $history->alarmCount, ['count' => $history->alarmCount]) }}
                </span>
            @endif
            @if ($history->openFollowUpCount > 0)
                <span class="wp-pill wp-pill--new wp-tabular">
                    {{ trans_choice('esg.point.open_follow_ups', $history->openFollowUpCount, ['count' => $history->openFollowUpCount]) }}
                </span>
            @endif
        </div>
    </div>

    @if ($history->trendPoints !== [])
        <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-stack-tight">
                <p class="wp-section-title">{{ __('esg.point.trend.title') }}</p>
                <p class="wp-muted wp-text-sm">
                    {{ trans_choice('esg.point.trend.period', $history->trendPeriodDays, ['days' => $history->trendPeriodDays]) }}
                    @if ($history->selectedIndicatorName)
                        · {{ $history->selectedIndicatorName }}
                    @endif
                </p>
            </div>
            <x-wp-esg-trend-chart
                :points="$history->trendPoints"
                :unit="$history->selectedIndicatorUnit"
            />
            @if ($history->selectedIndicatorId)
                <div class="wp-cluster wp-cluster--end">
                    <a href="{{ route('esg.measurements.index', [
                        'unit' => $history->unit->id,
                        'indicator' => $history->selectedIndicatorId,
                        'from' => now()->subDays($history->trendPeriodDays)->format('Y-m-d'),
                    ]) }}" class="btn btn--ghost btn--sm">
                        {{ __('esg.point.all_measurements') }}
                    </a>
                </div>
            @endif
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack-tight">
        <p class="wp-section-title">{{ __('esg.point.timeline.title') }}</p>

        @if ($history->measurements->isEmpty())
            <p class="wp-muted">{{ __('esg.point.timeline.empty') }}</p>
        @else
            <ul class="wp-list-plain wp-stack-tight">
                @foreach ($history->measurements as $measurement)
                    <li class="wp-list-row wp-esg-point-timeline__row" wire:key="esg-point-{{ $measurement->id }}">
                        <div class="wp-grow wp-stack-tight">
                            <strong>{{ $measurement->indicator?->localizedName() ?? '—' }}</strong>
                            <p class="wp-muted wp-text-sm">
                                {{ EsgMeasurementPresenter::displayValue($measurement) }}
                                · {{ optional($measurement->recorded_at)->format('d-m-Y H:i') }}
                            </p>
                            @include('partials.wp-esg-operation-chain', [
                                'steps' => EsgOperationChainPresenter::stepsForMeasurement($measurement),
                            ])
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
