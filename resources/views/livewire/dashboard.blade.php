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
            ['key' => 'locations', 'icon' => 'locations', 'label' => 'dashboard.kpi.locations'],
            ['key' => 'units', 'icon' => 'units', 'label' => 'dashboard.kpi.units'],
            ['key' => 'new_issues', 'icon' => 'issues', 'label' => 'dashboard.kpi.new_issues'],
            ['key' => 'open_tasks', 'icon' => 'tasks', 'label' => 'dashboard.kpi.open_tasks'],
        ];
        $highlightCutoff = now()->subHours(3);
    @endphp

    <div class="wp-kpis">
        @foreach ($kpis as $kpi)
            <a href="{{ $kpiLinks[$kpi['key']] }}"
               class="wp-kpi @if ($kpi['key'] === 'new_issues' && $stats['new_issues'] > 0) wp-kpi--alert @endif"
               wire:key="kpi-{{ $kpi['key'] }}">
                <span class="wp-kpi-icon">
                    <x-wp-icon :name="$kpi['icon']" />
                </span>
                <span class="wp-kpi-value wp-tabular">{{ $stats[$kpi['key']] }}</span>
                <span class="wp-kpi-label">{{ __($kpi['label']) }}</span>
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

    @if (file_exists(public_path('images/Winprox_logo_200.png')))
        <img src="{{ asset('images/Winprox_logo_200.png') }}" alt="WinProx" class="wp-brand-float" width="80" height="80" />
    @endif
</div>
