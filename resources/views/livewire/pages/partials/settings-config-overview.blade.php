@php
    $report = $configSummary->report;
    $previewIssues = array_slice($report->issues, 0, 5);
@endphp

<div class="wp-health-summary">
    <x-wp-health-donut
        :percent-complete="$report->percentComplete()"
        :incomplete-fraction="$report->incompleteFraction()"
        size="lg"
    />
    <div class="wp-stack-tight wp-grow">
        <p class="wp-section-title">{{ __('health.summary.title') }}</p>
        <p class="wp-kpi-value wp-tabular">{{ $report->percentComplete() }}%</p>
        <p class="wp-muted">
            {{ trans_choice('health.summary.complete', $report->completeChecks, [
                'complete' => $report->completeChecks,
                'total' => $report->totalChecks,
            ]) }}
        </p>
        @if ($report->isHealthy())
            <p class="wp-muted">{{ __('health.summary.all_ok') }}</p>
        @else
            <p class="wp-muted">{{ trans_choice('health.summary.issues', $report->issueCount, ['count' => $report->issueCount]) }}</p>
        @endif
    </div>
</div>

<x-wp-config-overview-kpis :summary="$configSummary" />

@if ($previewIssues !== [])
    <div class="wp-stack-tight">
        <h3 class="wp-section-title">{{ __('settings.config_overview.preview_title') }}</h3>
        <div class="wp-list wp-list--entity-rows">
            @foreach ($previewIssues as $issue)
                <div class="wp-issue-row" wire:key="settings-config-{{ $issue->type->value }}-{{ $issue->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-issue-card-title">{{ $issue->title }}</p>
                        <p class="wp-issue-card-meta">
                            <span class="wp-badge wp-badge-warning">{{ __($issue->type->labelKey()) }}</span>
                            <span>{{ $issue->subtitle }}</span>
                        </p>
                    </div>
                    <div class="wp-cluster">
                        <a href="{{ $issue->fixUrl }}" class="btn btn--primary btn--sm">{{ __('health.fix') }}</a>
                    </div>
                </div>
            @endforeach
        </div>
        @if ($report->issueCount > count($previewIssues))
            <p class="wp-muted wp-text-sm">{{ __('settings.config_overview.more_issues', ['count' => $report->issueCount - count($previewIssues)]) }}</p>
        @endif
    </div>
@endif

<div class="wp-cluster">
    <a href="{{ route('health.index') }}" class="btn btn--ghost btn--sm">{{ __('settings.config_overview.open_full') }}</a>
</div>
