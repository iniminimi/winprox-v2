<div class="wp-stack">
    <div class="wp-page-head">
        <div class="wp-stack-tight">
            <h1 class="wp-page-title">{{ __('dashboard.title') }}</h1>
            <p class="wp-muted">{{ __('dashboard.subtitle') }}</p>
        </div>
        <div class="wp-cluster">
            @if ($trialDays !== null)
                <span class="wp-pill wp-pill--progress">{{ __('dashboard.trial', ['days' => $trialDays]) }}</span>
            @endif
            @if ($subscriptionGrace)
                <span class="wp-pill wp-pill--closed">{{ __('dashboard.grace') }}</span>
            @endif
            <a href="{{ route('issues.index', ['create' => 1]) }}" class="btn btn--primary btn--sm">
                <x-wp-icon name="plus" class="wp-icon" />
                <span>{{ __('dashboard.add_issue') }}</span>
            </a>
            <a href="{{ route('briefing.print') }}" target="_blank" class="btn btn--ghost btn--sm">{{ __('dashboard.briefing_print') }}</a>
        </div>
    </div>

    @if ($unitLimitWarning || $userLimitWarning)
        <div class="wp-flash {{ ($unitLimitWarning === 'critical' || $userLimitWarning === 'critical') ? 'wp-flash--danger' : 'wp-flash--muted' }}">
            @if ($unitLimitWarning)
                <p>{{ $unitLimitWarning === 'critical' ? __('subscription.limits.critical_units', ['remaining' => $remainingUnits ?? 0]) : __('subscription.limits.warning_units', ['remaining' => $remainingUnits ?? 0]) }}</p>
            @endif
            @if ($userLimitWarning)
                <p>{{ $userLimitWarning === 'critical' ? __('subscription.limits.critical_users', ['remaining' => $remainingUsers ?? 0]) : __('subscription.limits.warning_users', ['remaining' => $remainingUsers ?? 0]) }}</p>
            @endif
            <p><a href="{{ route('subscription.index') }}" class="btn btn--ghost btn--sm">{{ __('subscription.limits.cta') }}</a></p>
        </div>
    @endif

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

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('dashboard.recent.title') }}</h2>
            <a href="{{ route('issues.index') }}" class="btn btn--ghost btn--sm">{{ __('dashboard.recent.open_issues') }}</a>
        </div>

        <div class="wp-list">
            @forelse ($recent as $issue)
                <a href="{{ route('issues.show', $issue) }}"
                   class="wp-issue-row @if ($issue->created_at?->gte($highlightCutoff)) wp-issue-row--highlight @endif"
                   wire:key="recent-{{ $issue->id }}">
                    <div class="wp-grow">
                        <p class="wp-issue-desc">{{ \Illuminate\Support\Str::limit($issue->description, 90) }}</p>
                        <p class="wp-muted">
                            {{ $issue->location?->name ?? __('dashboard.recent.no_location') }}@if ($issue->unit) &middot; {{ $issue->unit->name }}@endif@if ($issue->location?->formattedAddress()) &middot; {{ $issue->location->formattedAddress() }}@endif
                        </p>
                    </div>
                    <div class="wp-issue-row-meta">
                        <x-wp-ref-nr :id="$issue->id" />
                        <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
                        <span class="wp-muted">{{ $issue->created_at?->format('d-m-Y') }}</span>
                        <span class="wp-muted">{{ $issue->reporter_name ?: __('dashboard.recent.reporter_anon') }}</span>
                    </div>
                </a>
            @empty
                <p class="wp-muted">{{ __('dashboard.recent.empty') }}</p>
            @endforelse
        </div>
    </div>

</div>
