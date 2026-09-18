@php
    $issue = $task->issue;
    $location = $issue?->location;
    $teamName = $task->team?->localizedName();
    $metaParts = array_values(array_filter([
        $location?->localizedName(),
        $issue ? __('issues.card.round_stops', ['count' => $issue->roundStopCount()]) : null,
        $teamName,
        ($task->scheduled_for || $task->due_at)
            ? __('tasks.show.due', ['date' => ($task->scheduled_for ?? $task->due_at)?->format('d/m/Y')])
            : null,
        $task->is_recurring_cycle
            ? __('tasks.show.recurring_cycle', ['nr' => $task->cycle_number ?? 1])
            : null,
    ]));
@endphp

<x-wp-report-print
    :title="__('reports.inspection_round_task.title')"
    :document-title="__('reports.inspection_round_task.document_title')"
    :tenant="$tenant"
>
    <div class="wp-card wp-card-pad wp-stack-tight wp-report-print__card">
        @if (filled($issue?->localizedDescription()))
            <p class="wp-text-body">{{ $issue->localizedDescription() }}</p>
        @endif
        @if ($metaParts !== [])
            <p class="wp-muted">{{ implode(' · ', $metaParts) }}</p>
        @endif
        <h2 class="wp-section-title">{{ __('tasks.show.round_progress') }}</h2>
        @include('partials.wp-portal-round-progress', [
            'progress' => $progress,
        ])
    </div>
</x-wp-report-print>
