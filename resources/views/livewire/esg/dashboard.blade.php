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
                    <x-wp-icon name="arrow-right" class="wp-health-widget__chevron" />
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
