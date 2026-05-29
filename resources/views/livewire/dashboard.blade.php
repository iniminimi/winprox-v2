<div class="wp-stack">
    <div class="wp-row">
        <h1 class="wp-page-title">{{ __('dashboard.title') }}</h1>
        <a href="{{ route('issues.index') }}" class="btn btn--ghost btn--sm">{{ __('dashboard.view_issues') }}</a>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('dashboard.by_status') }}</h2>
        <div class="wp-stats">
            @foreach ($statuses as $status)
                <div class="wp-stat">
                    <span class="wp-stat-value">{{ $stats[$status->value] }}</span>
                    <span class="wp-pill wp-pill--{{ $status->pillModifier() }}">{{ __($status->labelKey()) }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="wp-card wp-card-pad">
        <div class="wp-stat">
            <span class="wp-stat-value">{{ $total }}</span>
            <span class="wp-stat-label">{{ __('dashboard.total') }}</span>
        </div>
    </div>
</div>
