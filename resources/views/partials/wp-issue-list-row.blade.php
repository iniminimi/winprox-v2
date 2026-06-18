@php
    use App\Enums\IssueSource;
    use App\Enums\TaskStatus;

    // Exclude closed tasks from display
    $openTasks = $issue->tasks->filter(fn ($t) => $t->status !== TaskStatus::Closed);

    $teamNames = $issue->isApproved()
        ? $openTasks->map(fn ($t) => $t->team?->name)->filter()->unique()->values()
        : collect();
    $cardTitle = collect([
        $issue->location?->name,
        $issue->unit?->name,
        __('issues.card.kind_nr', ['nr' => $issue->id]),
    ])->filter()->join(', ');
    $addressLine = $issue->location
        ? trim(($issue->location->country_code ?: 'BE').' '.$issue->location->formattedAddress())
        : '';
    $datetime = optional($issue->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i');
    $reporterName = $issue->reporter_name ?: __('issues.card.unknown_reporter');
    $issueLine = match ($issue->source) {
        IssueSource::Qr => __('issues.card.line_reported_by', ['name' => $reporterName]),
        default => __('issues.card.line_created_by', ['name' => $reporterName]),
    };
    $contextMeta = match (true) {
        $issue->source === IssueSource::Qr && ! $issue->isApproved() => __('issues.card.meta_via_context_no_team', [
            'source' => __('issues.card.report_source_qr'),
            'datetime' => $datetime,
        ]),
        $issue->source === IssueSource::Qr => __('issues.card.meta_via_context', [
            'source' => __('issues.card.report_source_qr'),
            'datetime' => $datetime,
            'teams' => $teamNames->isNotEmpty() ? $teamNames->join(', ') : __('issues.card.no_team'),
        ]),
        ! $issue->isApproved() => __('issues.card.meta_context_no_team', [
            'datetime' => $datetime,
        ]),
        default => __('issues.card.meta_context', [
            'datetime' => $datetime,
            'teams' => $teamNames->isNotEmpty() ? $teamNames->join(', ') : __('issues.card.no_team'),
        ]),
    };
    // Get highest priority task for this issue (excluding closed tasks)
    $highestPriorityTask = $openTasks->sortBy(fn ($t) => $t->priority?->sortOrder() ?? 99)->first();
@endphp
<a href="{{ route('issues.show', $issue) }}"
   @class(['wp-issue-row', 'wp-issue-row--highlight' => $highlight ?? false])
   wire:key="issue-{{ $issue->id }}">
    <div class="wp-grow wp-stack-tight">
        @if ($cardTitle !== '')
            <p class="wp-issue-card-title">{{ $cardTitle }}</p>
        @endif
        @if ($addressLine !== '')
            <p class="wp-issue-card-meta">{{ $addressLine }}</p>
        @endif
        <p class="wp-issue-card-meta">{{ $issueLine }}</p>
        <p class="wp-issue-card-meta">{{ $contextMeta }}</p>
        @if ($issue->localizedDescription())
            <p class="wp-issue-card-desc">{{ $issue->localizedDescription() }}</p>
        @endif
    </div>
    <div class="wp-issue-row-meta">
        @unless ($issue->isApproved())
            <span class="wp-pill wp-pill--progress">{{ __('issues.pending_review') }}</span>
        @endunless
        @if ($issue->is_recurring)
            <span class="wp-pill wp-pill--done">{{ __('issues.card.recurring') }}</span>
        @endif
        @if ($highestPriorityTask && $highestPriorityTask->priority && $issue->isApproved())
            <span class="wp-badge {{ $highestPriorityTask->priority->badgeClass() }}">
                <x-wp-icon :name="$highestPriorityTask->priority->icon()" class="wp-icon wp-icon--sm" />
                {{ $highestPriorityTask->priority->label() }}
            </span>
        @endif
        <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
    </div>
</a>
