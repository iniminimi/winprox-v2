@php
    use App\Support\Esg\EsgMeasurementPresenter;
@endphp

<div class="wp-stack" data-manual-capture="esg-dashboard">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="sliders"
                :title="__('esg.title')"
                :subtitle="__('esg.dashboard.subtitle')"
            />
        </div>
        <div class="wp-cluster">
            @can('create', App\Models\EsgIndicator::class)
                <a href="{{ route('esg.indicators.index') }}" class="btn btn--primary btn--sm">
                    {{ __('esg.add') }}
                </a>
            @endcan
            <a href="{{ route('esg.measurements.index') }}" class="btn btn--ghost btn--sm">
                {{ __('esg.nav.measurements') }}
            </a>
        </div>
    </div>

    @include('partials.wp-esg-subnav')

    @if ($dashboard->showSetup)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <p class="wp-muted">{{ __('esg.dashboard.empty') }}</p>
            @include('partials.wp-esg-setup-steps', ['setupKey' => 'esg.setup'])
        </div>
    @endif

    <div class="wp-kpis">
        <a href="{{ route('esg.measurements.index') }}"
           class="wp-kpi @if ($dashboard->alarmCount > 0) wp-kpi--alert @endif"
           wire:key="kpi-alarms">
            <div class="wp-kpi-body">
                <div class="wp-kpi-main">
                    <p class="wp-kpi-kicker">{{ __('esg.dashboard.kpi.alarms') }}</p>
                    <p class="wp-kpi-stats">
                        <span class="wp-kpi-value wp-tabular">{{ $dashboard->alarmCount }}</span>
                        @if ($dashboard->thresholdSampleSize > 0)
                            <span class="wp-kpi-meta">
                                {{ trans_choice('esg.dashboard.kpi.alarms_meta', $dashboard->thresholdSampleSize, [
                                    'count' => $dashboard->thresholdSampleSize,
                                ]) }}
                            </span>
                        @endif
                    </p>
                </div>
                <span class="wp-kpi-icon" aria-hidden="true">
                    <x-wp-icon name="alert-triangle" />
                </span>
            </div>
        </a>

        @foreach ($dashboard->indicatorKpis as $kpi)
            <a href="{{ route('esg.measurements.index', ['indicator' => $kpi['indicator_id']]) }}"
               class="wp-kpi @if ($kpi['is_alert']) wp-kpi--alert @endif"
               wire:key="kpi-indicator-{{ $kpi['indicator_id'] }}">
                <div class="wp-kpi-body">
                    <div class="wp-kpi-main">
                        <p class="wp-kpi-kicker">{{ $kpi['name'] }}</p>
                        <p class="wp-kpi-stats">
                            <span class="wp-kpi-value wp-tabular">{{ $kpi['value'] }}</span>
                            @if ($kpi['has_measurement'] && $kpi['recorded_at_label'])
                                <span class="wp-kpi-meta">{{ $kpi['recorded_at_label'] }}</span>
                            @elseif (! $kpi['has_measurement'])
                                <span class="wp-kpi-meta">{{ __('esg.dashboard.kpi.no_reading') }}</span>
                            @endif
                        </p>
                    </div>
                    <span class="wp-kpi-icon" aria-hidden="true">
                        <x-wp-icon name="sliders" />
                    </span>
                </div>
            </a>
        @endforeach
    </div>

    @if ($dashboard->trendIndicatorOptions !== [])
        <div @class([
            'wp-dashboard-widgets',
            'wp-dashboard-widgets--trend' => $dashboard->thresholdSampleSize > 0,
            'wp-dashboard-widgets--single' => $dashboard->thresholdSampleSize === 0,
        ])>
            <div class="wp-card wp-card-pad wp-stack-tight" wire:key="esg-trend">
                <div class="wp-row">
                    <div class="wp-stack-tight wp-grow">
                        <p class="wp-section-title">{{ __('esg.dashboard.trend.title') }}</p>
                        <p class="wp-muted wp-text-sm">
                            {{ trans_choice('esg.dashboard.trend.period', $dashboard->trendPeriodDays, [
                                'days' => $dashboard->trendPeriodDays,
                            ]) }}
                        </p>
                    </div>
                    <div class="wp-filter-field wp-esg-trend__select">
                        <label class="wp-label" for="esg-trend-indicator">{{ __('esg.dashboard.trend.indicator') }}</label>
                        <select id="esg-trend-indicator" class="wp-input" wire:model.live="trendIndicatorId">
                            @foreach ($dashboard->trendIndicatorOptions as $option)
                                <option value="{{ $option['id'] }}">{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <x-wp-esg-trend-chart
                    :points="$dashboard->trendPoints"
                    :unit="$dashboard->selectedTrendUnit"
                />
            </div>

            @if ($dashboard->thresholdSampleSize > 0)
                <a href="{{ route('esg.measurements.index') }}"
                   class="wp-dashboard-widget wp-health-widget wp-card wp-card-pad"
                   wire:key="esg-threshold-widget">
                    <div class="wp-health-widget__body">
                        <x-wp-health-donut
                            size="sm"
                            :percent-complete="$dashboard->thresholdOkPercent"
                            :incomplete-fraction="$dashboard->thresholdIncompleteFraction"
                        />
                        <div class="wp-stack-tight wp-grow">
                            <p class="wp-kpi-kicker">{{ __('esg.dashboard.threshold.kicker') }}</p>
                            <p class="wp-dashboard-widget__title">{{ __('esg.dashboard.threshold.title') }}</p>
                            <p class="wp-muted">
                                {{ trans_choice('esg.dashboard.threshold.summary', $dashboard->alarmCount, [
                                    'count' => $dashboard->alarmCount,
                                ]) }}
                            </p>
                        </div>
                        <x-wp-icon name="chevron-down" class="wp-health-widget__chevron" />
                    </div>
                </a>
            @endif
        </div>

        <div class="wp-card wp-card-pad wp-stack-tight" wire:key="esg-top-locations">
            <div class="wp-row">
                <p class="wp-section-title">{{ __('esg.dashboard.top_locations.title') }}</p>
                @if ($dashboard->selectedTrendIndicatorId)
                    <a href="{{ route('esg.measurements.index', ['indicator' => $dashboard->selectedTrendIndicatorId]) }}"
                       class="btn btn--ghost btn--sm">
                        {{ __('esg.dashboard.top_locations.all') }}
                    </a>
                @endif
            </div>

            @if ($dashboard->topLocations === [])
                <p class="wp-muted">{{ __('esg.dashboard.top_locations.empty') }}</p>
            @else
                <div class="wp-list wp-list--entity-rows wp-esg-top-locations__list">
                    @foreach ($dashboard->topLocations as $row)
                        <a href="{{ $row['measurements_url'] }}" class="wp-traffic-row" wire:key="esg-top-location-{{ $row['location_id'] }}">
                            <div class="wp-grow wp-stack-tight">
                                <p class="wp-issue-card-title">{{ $row['name'] }}</p>
                                <p class="wp-issue-card-meta">
                                    {{ trans_choice('esg.dashboard.top_locations.readings', $row['measurement_count'], [
                                        'count' => $row['measurement_count'],
                                    ]) }}
                                </p>
                            </div>
                            <div class="wp-cluster">
                                <span class="wp-pill wp-pill--closed wp-tabular">{{ $row['total_formatted'] }}</span>
                                <x-wp-icon name="chevron-down" class="wp-traffic-row__chevron" />
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if ($dashboard->showComplianceScore || $dashboard->categorySegments !== [])
        <div @class([
            'wp-dashboard-widgets',
            'wp-dashboard-widgets--donuts',
            'wp-dashboard-widgets--single' => ! $dashboard->showComplianceScore || $dashboard->categorySegments === [],
        ])>
            @if ($dashboard->showComplianceScore)
                <div class="wp-dashboard-widget wp-card wp-card-pad wp-esg-score-widget" wire:key="esg-compliance-score">
                    <div class="wp-esg-score-widget__body">
                        <x-wp-health-donut
                            size="lg"
                            :percent-complete="$dashboard->complianceScore"
                            :incomplete-fraction="$dashboard->complianceIncompleteFraction"
                        />
                        <div class="wp-stack-tight wp-grow">
                            <p class="wp-kpi-kicker">{{ __('esg.dashboard.score.kicker') }}</p>
                            <p class="wp-dashboard-widget__title">{{ __('esg.dashboard.score.title') }}</p>
                            <p class="wp-muted wp-text-sm">{{ __('esg.dashboard.score.subtitle') }}</p>
                            <ul class="wp-esg-score-widget__breakdown wp-muted wp-text-sm">
                                @if ($dashboard->complianceThresholdPercent !== null)
                                    <li>
                                        {{ __('esg.dashboard.score.threshold', [
                                            'percent' => $dashboard->complianceThresholdPercent,
                                        ]) }}
                                    </li>
                                @endif
                                @if ($dashboard->complianceCoveragePercent !== null)
                                    <li>
                                        {{ __('esg.dashboard.score.coverage', [
                                            'percent' => $dashboard->complianceCoveragePercent,
                                        ]) }}
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            @if ($dashboard->categorySegments !== [])
                <div class="wp-dashboard-widget wp-card wp-card-pad wp-stack-tight" wire:key="esg-category-distribution">
                    <div class="wp-stack-tight">
                        <p class="wp-kpi-kicker">{{ __('esg.dashboard.distribution.kicker') }}</p>
                        <p class="wp-dashboard-widget__title">{{ __('esg.dashboard.distribution.title') }}</p>
                        <p class="wp-muted wp-text-sm">
                            {{ trans_choice('esg.dashboard.distribution.period', $dashboard->trendPeriodDays, [
                                'days' => $dashboard->trendPeriodDays,
                            ]) }}
                        </p>
                    </div>
                    <x-wp-segment-donut
                        :segments="$dashboard->categorySegments"
                        :center-label="(string) $dashboard->categoryMeasurementTotal"
                        size="md"
                    />
                </div>
            @endif
        </div>
    @endif

    @if ($dashboard->trendIndicatorOptions !== [])
        <div class="wp-card wp-card-pad wp-stack-tight" wire:key="esg-open-tasks-widget">
            <div class="wp-row">
                <p class="wp-section-title">{{ __('esg.dashboard.open_tasks.title') }}</p>
                <a href="{{ route('tasks.index') }}" class="btn btn--ghost btn--sm">
                    {{ __('esg.dashboard.open_tasks.all') }}
                </a>
            </div>

            @if ($dashboard->openEsgTasks->isEmpty())
                <p class="wp-muted">{{ __('esg.dashboard.open_tasks.empty') }}</p>
            @else
                <ul class="wp-list-plain wp-stack-tight">
                    @foreach ($dashboard->openEsgTasks as $task)
                        <li class="wp-list-row" wire:key="esg-open-task-{{ $task->id }}">
                            <div>
                                <strong>{{ $task->issue?->esgIndicator?->localizedName() ?? '—' }}</strong>
                                <p class="wp-muted wp-text-sm">
                                    {{ $task->issue?->location?->localizedName() ?? '—' }}
                                    @if ($task->issue?->unit)
                                        · {{ $task->issue->unit->localizedName() }}
                                    @endif
                                </p>
                                @if ($task->due_at)
                                    <p class="wp-muted wp-text-sm">
                                        {{ __('esg.dashboard.open_tasks.due', ['date' => $task->due_at->format('d-m-Y')]) }}
                                    </p>
                                @endif
                            </div>
                            <a href="{{ route('tasks.show', $task) }}" class="btn btn--ghost btn--sm">
                                {{ __('esg.measurements.view_task') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @else
        <div @class([
            'wp-dashboard-widgets',
            'wp-dashboard-widgets--single' => $dashboard->thresholdSampleSize === 0 && $dashboard->openEsgTasks->isEmpty(),
        ])>
            @if ($dashboard->thresholdSampleSize > 0)
                <a href="{{ route('esg.measurements.index') }}"
                   class="wp-dashboard-widget wp-health-widget wp-card wp-card-pad"
                   wire:key="esg-threshold-widget">
                    <div class="wp-health-widget__body">
                        <x-wp-health-donut
                            size="sm"
                            :percent-complete="$dashboard->thresholdOkPercent"
                            :incomplete-fraction="$dashboard->thresholdIncompleteFraction"
                        />
                        <div class="wp-stack-tight wp-grow">
                            <p class="wp-kpi-kicker">{{ __('esg.dashboard.threshold.kicker') }}</p>
                            <p class="wp-dashboard-widget__title">{{ __('esg.dashboard.threshold.title') }}</p>
                            <p class="wp-muted">
                                {{ trans_choice('esg.dashboard.threshold.summary', $dashboard->alarmCount, [
                                    'count' => $dashboard->alarmCount,
                                ]) }}
                            </p>
                        </div>
                        <x-wp-icon name="chevron-down" class="wp-health-widget__chevron" />
                    </div>
                </a>
            @endif

            <div class="wp-dashboard-widget wp-card wp-card-pad wp-stack-tight" wire:key="esg-open-tasks-widget">
                <div class="wp-row">
                    <p class="wp-section-title">{{ __('esg.dashboard.open_tasks.title') }}</p>
                    <a href="{{ route('tasks.index') }}" class="btn btn--ghost btn--sm">
                        {{ __('esg.dashboard.open_tasks.all') }}
                    </a>
                </div>

                @if ($dashboard->openEsgTasks->isEmpty())
                    <p class="wp-muted">{{ __('esg.dashboard.open_tasks.empty') }}</p>
                @else
                    <ul class="wp-list-plain wp-stack-tight">
                        @foreach ($dashboard->openEsgTasks as $task)
                            <li class="wp-list-row" wire:key="esg-open-task-{{ $task->id }}">
                                <div>
                                    <strong>{{ $task->issue?->esgIndicator?->localizedName() ?? '—' }}</strong>
                                    <p class="wp-muted wp-text-sm">
                                        {{ $task->issue?->location?->localizedName() ?? '—' }}
                                        @if ($task->issue?->unit)
                                            · {{ $task->issue->unit->localizedName() }}
                                        @endif
                                    </p>
                                    @if ($task->due_at)
                                        <p class="wp-muted wp-text-sm">
                                            {{ __('esg.dashboard.open_tasks.due', ['date' => $task->due_at->format('d-m-Y')]) }}
                                        </p>
                                    @endif
                                </div>
                                <a href="{{ route('tasks.show', $task) }}" class="btn btn--ghost btn--sm">
                                    {{ __('esg.measurements.view_task') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif

    <div class="wp-dashboard-widgets">
        <div class="wp-card wp-card-pad wp-stack-tight" wire:key="esg-recent-measurements">
            <div class="wp-row">
                <p class="wp-section-title">{{ __('esg.dashboard.recent.title') }}</p>
                <a href="{{ route('esg.measurements.index') }}" class="btn btn--ghost btn--sm">
                    {{ __('esg.dashboard.recent.all') }}
                </a>
            </div>

            @if ($dashboard->recentMeasurements->isEmpty())
                <p class="wp-muted">{{ __('esg.measurements.empty') }}</p>
            @else
                <ul class="wp-list-plain wp-stack-tight">
                    @foreach ($dashboard->recentMeasurements as $measurement)
                        @php($outsideThresholds = EsgMeasurementPresenter::isOutsideThresholds($measurement))
                        <li class="wp-list-row" wire:key="esg-recent-{{ $measurement->id }}">
                            <div>
                                <strong>{{ $measurement->indicator?->localizedName() ?? '—' }}</strong>
                                <p class="wp-muted wp-text-sm">
                                    {{ EsgMeasurementPresenter::displayValue($measurement) }}
                                </p>
                                <p class="wp-muted wp-text-sm">
                                    {{ $measurement->location?->localizedName() ?? '—' }}
                                    @if ($measurement->unit)
                                        · {{ $measurement->unit->localizedName() }}
                                    @endif
                                    · {{ optional($measurement->recorded_at)->format('d-m-Y H:i') }}
                                </p>
                            </div>
                            <div class="wp-cluster">
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
            @endif
        </div>

        <div class="wp-card wp-card-pad wp-stack-tight" wire:key="esg-alarms">
            <div class="wp-row">
                <p class="wp-section-title">{{ __('esg.dashboard.alarms.title') }}</p>
                <a href="{{ route('esg.measurements.index') }}" class="btn btn--ghost btn--sm">
                    {{ __('esg.dashboard.alarms.all') }}
                </a>
            </div>

            @if ($dashboard->alarmMeasurements->isEmpty())
                <p class="wp-muted">{{ __('esg.dashboard.alarms.empty') }}</p>
            @else
                <ul class="wp-list-plain wp-stack-tight">
                    @foreach ($dashboard->alarmMeasurements as $measurement)
                        <li class="wp-list-row" wire:key="esg-alarm-{{ $measurement->id }}">
                            <div>
                                <strong>{{ $measurement->indicator?->localizedName() ?? '—' }}</strong>
                                <p class="wp-muted wp-text-sm">
                                    {{ EsgMeasurementPresenter::displayValue($measurement) }}
                                </p>
                                <p class="wp-muted wp-text-sm">
                                    {{ $measurement->location?->localizedName() ?? '—' }}
                                    @if ($measurement->unit)
                                        · {{ $measurement->unit->localizedName() }}
                                    @endif
                                    · {{ optional($measurement->recorded_at)->format('d-m-Y H:i') }}
                                </p>
                            </div>
                            @if ($measurement->task_id)
                                <a href="{{ route('tasks.show', $measurement->task_id) }}" class="btn btn--ghost btn--sm">
                                    {{ __('esg.measurements.view_task') }}
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
