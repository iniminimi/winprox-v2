<div class="wp-stack" data-manual-capture="health">
    <div class="wp-page-head">
        <div class="wp-grow wp-stack-tight">
            <x-wp-page-head-title
                icon="settings"
                :title="__('health.title')"
                help-page="health"
                :subtitle="__('health.subtitle')"
            />
        </div>
    </div>

    <div class="wp-card wp-card-pad wp-stack">
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
    </div>

    @if (! $report->isHealthy())
        <div class="wp-card wp-card-pad wp-stack">
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('health.list.title') }}</h2>
            </div>

            <div class="wp-filter-row">
                <label class="wp-field">
                    <span class="wp-label">{{ __('health.filter.label') }}</span>
                    <select class="wp-input" wire:model.live="filter">
                        <option value="">{{ __('health.filter.all') }}</option>
                        @foreach ($filterOptions as $option)
                            <option value="{{ $option->value }}">{{ __($option->labelKey()) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="wp-list wp-list--entity-rows">
                @forelse ($issues as $issue)
                    <div class="wp-issue-row" wire:key="health-{{ $issue->type->value }}-{{ $issue->id }}">
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
                @empty
                    <p class="wp-muted">{{ __('health.list.empty_filter') }}</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
