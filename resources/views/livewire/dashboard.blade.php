<div class="wp-stack">
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
                <x-wp-icon name="plus" class="wp-icon" />
                <span>{{ __('dashboard.add_issue') }}</span>
            </a>
            <a href="{{ route('briefing.print') }}" target="_blank" class="btn btn--ghost btn--sm">{{ __('dashboard.briefing_print') }}</a>
        </div>
    </div>

    @php
        $kpiLinks = [
            'locations' => route('locations.index'),
            'units' => route('locations.index'),
            'new_issues' => route('issues.index', ['status' => 'new']),
            'open_tasks' => route('tasks.index', ['status' => 'in_progress']),
        ];
        $kpis = [
            ['key' => 'locations', 'icon' => 'locations', 'label' => 'dashboard.kpi.locations', 'meta' => 'dashboard.kpi.meta_total'],
            ['key' => 'units', 'icon' => 'units', 'label' => 'dashboard.kpi.units', 'meta' => 'dashboard.kpi.meta_total'],
            ['key' => 'new_issues', 'icon' => 'issues', 'label' => 'dashboard.kpi.new_issues', 'meta' => null],
            ['key' => 'open_tasks', 'icon' => 'tasks', 'label' => 'dashboard.kpi.open_tasks', 'meta' => 'dashboard.kpi.meta_in_progress'],
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

    @if ($hasNoLocationsOrUnits)
        <div class="wp-card wp-card-pad wp-onboarding-card">
            <div class="wp-stack">
                <p class="wp-text-body"><strong>{{ __('dashboard.onboarding.title') }}</strong></p>
                <p class="wp-muted">{{ __('dashboard.onboarding.text') }}</p>
                <a href="{{ route('locations.index') }}" class="btn btn--primary btn--sm">
                    {{ __('dashboard.onboarding.button') }}
                </a>
            </div>
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

</div>
