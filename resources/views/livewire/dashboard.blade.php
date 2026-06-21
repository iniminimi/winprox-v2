<div class="wp-stack" data-manual-capture="dashboard">
    @if ($onboarding->showTeamsBanner())
        <x-wp-onboarding-banner stage="teams" />
    @elseif ($onboarding->showCategoriesBanner())
        <x-wp-onboarding-banner stage="categories" />
    @endif

    @if ($onboarding->showWelcomeGuide)
        <div class="wp-stack-loose">
            <h1 class="text-4xl font-bold text-gray-900">{{ __('dashboard.welcome') }}</h1>
        </div>

        <div class="wp-card wp-card-pad">
            <div class="wp-stack">
                <h2 class="wp-section-title">{{ __('manual.getting_started.label') }}</h2>
                <p class="wp-text-body"><strong>{{ __('manual.getting_started.title') }}</strong></p>
                <p class="wp-muted">{{ __('manual.getting_started.intro') }}</p>

                <div class="wp-stack-tight">
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_1_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_1_text') }}</p>
                    </div>
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_2_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_2_text') }}</p>
                    </div>
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_3_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_3_text') }}</p>
                    </div>
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_4_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_4_text') }}</p>
                    </div>
                    <div class="wp-stack-tight">
                        <p class="wp-text-body"><strong>{{ __('manual.step_5_title') }}</strong></p>
                        <p class="wp-muted">{{ __('manual.step_5_text') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (! $onboarding->blocksDashboardMain())
        <div class="wp-page-head">
            <div class="wp-grow wp-stack-tight">
                <x-wp-page-head-title
                    icon="dashboard"
                    :title="__('dashboard.title')"
                    help-page="dashboard"
                    :subtitle="__('dashboard.subtitle')"
                />
            </div>
            <div class="wp-cluster">
                @if ($portalBatteryState)
                    <x-wp-trial-battery-capsule :state="$portalBatteryState" />
                @endif
                <a href="{{ route('issues.index', ['create' => 1]) }}" class="btn btn--primary btn--sm">
                    {{ __('dashboard.add_issue') }}
                </a>
                <a href="{{ route('briefing.print') }}" target="_blank" class="btn btn--ghost btn--sm">{{ __('dashboard.briefing_print') }}</a>
            </div>
        </div>

        @php
            $kpiLinks = [
                'locations' => route('locations.index'),
                'units' => route('locations.index'),
                'new_issues' => route('issues.index', ['status' => 'new']),
                'open_tasks' => route('tasks.index'),
            ];
            $kpis = [
                ['key' => 'locations', 'icon' => 'locations', 'label' => 'dashboard.kpi.locations', 'meta' => 'dashboard.kpi.meta_total'],
                ['key' => 'units', 'icon' => 'units', 'label' => 'dashboard.kpi.units', 'meta' => 'dashboard.kpi.meta_total'],
                ['key' => 'new_issues', 'icon' => 'issues', 'label' => 'dashboard.kpi.new_issues', 'meta' => null],
                ['key' => 'open_tasks', 'icon' => 'tasks', 'label' => 'dashboard.kpi.open_tasks', 'meta' => null],
            ];
            $highlightCutoff = now()->subHours(3);
        @endphp

        <div class="wp-kpis">
            @foreach ($kpis as $kpi)
                <a href="{{ $kpiLinks[$kpi['key']] }}"
                   class="wp-kpi wp-kpi--{{ $kpi['key'] }} @if ($kpi['key'] === 'new_issues' && $stats['new_issues'] > 0) wp-kpi--alert @endif"
                   wire:key="kpi-{{ $kpi['key'] }}">
                    <div class="wp-kpi-body">
                        <div class="wp-kpi-main">
                            <p class="wp-kpi-kicker">{{ __($kpi['label']) }}</p>
                            <p class="wp-kpi-stats">
                                <span class="wp-kpi-value wp-tabular">{{ $stats[$kpi['key']] }}</span>
                                @if ($kpi['meta'])
                                    <span class="wp-kpi-meta">{{ __($kpi['meta']) }}</span>
                                @endif
                            </p>
                        </div>
                        <span class="wp-kpi-icon" aria-hidden="true">
                            <x-wp-icon :name="$kpi['icon']" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        @php
            $showHealthWidget = ! $health->isHealthy() && $health->totalChecks > 0;
            $showTrafficWidget = count($topScannedUnits) > 0;
            $showInsightWidgets = $showHealthWidget || $showTrafficWidget;
        @endphp

        @if ($showInsightWidgets)
            <div @class(['wp-dashboard-widgets', 'wp-dashboard-widgets--single' => ! $showHealthWidget || ! $showTrafficWidget])>
                @if ($showHealthWidget)
                    <a href="{{ route('health.index') }}" class="wp-dashboard-widget wp-health-widget wp-card wp-card-pad" wire:key="health-widget">
                        <div class="wp-health-widget__body">
                            <x-wp-health-donut
                                size="sm"
                                :percent-complete="$health->percentComplete()"
                                :incomplete-fraction="$health->incompleteFraction()"
                            />
                            <div class="wp-stack-tight wp-grow">
                                <p class="wp-kpi-kicker">{{ __('health.widget.kicker') }}</p>
                                <p class="wp-dashboard-widget__title">{{ __('health.widget.title') }}</p>
                                <p class="wp-muted">{{ trans_choice('health.widget.issues', $health->issueCount, ['count' => $health->issueCount]) }}</p>
                            </div>
                            <x-wp-icon name="arrow-right" class="wp-health-widget__chevron" />
                        </div>
                    </a>
                @endif

                @if ($showTrafficWidget)
                    <div class="wp-dashboard-widget wp-traffic-widget wp-card wp-card-pad" wire:key="traffic-widget">
                        <div class="wp-stack-tight">
                            <p class="wp-kpi-kicker">{{ __('dashboard.traffic.kicker') }}</p>
                            <p class="wp-dashboard-widget__title">{{ __('dashboard.traffic.title') }}</p>
                            <p class="wp-muted">{{ __('dashboard.traffic.subtitle') }}</p>
                        </div>
                        <div class="wp-list wp-list--entity-rows wp-traffic-widget__list">
                            @foreach ($topScannedUnits as $row)
                                <a href="{{ $row->detailUrl }}" class="wp-traffic-row" wire:key="traffic-unit-{{ $row->unitId }}">
                                    <div class="wp-grow wp-stack-tight">
                                        <p class="wp-issue-card-title">{{ $row->unitName }}</p>
                                        <p class="wp-issue-card-meta">{{ $row->locationName }}</p>
                                    </div>
                                    <div class="wp-cluster">
                                        <span class="wp-pill wp-pill--closed wp-tabular">{{ trans_choice('dashboard.traffic.scans', $row->scanCount, ['count' => $row->scanCount]) }}</span>
                                        <x-wp-icon name="arrow-right" class="wp-traffic-row__chevron" />
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('dashboard.recent.title') }}</h2>
                <a href="{{ route('issues.index') }}" class="btn btn--ghost btn--sm">{{ __('dashboard.recent.open_issues') }}</a>
            </div>

            <div class="wp-list wp-list--entity-rows">
                @forelse ($recent as $issue)
                    @include('partials.wp-issue-list-row', [
                        'issue' => $issue,
                        'highlight' => $issue->created_at?->gte($highlightCutoff),
                    ])
                @empty
                    <p class="wp-muted">{{ __('dashboard.recent.empty') }}</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
