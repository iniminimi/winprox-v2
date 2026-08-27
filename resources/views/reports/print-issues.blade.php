@php
    use App\Enums\TaskStatus;

    $pendingIssues = $issues->filter(
        fn ($issue) => ! $issue->isApproved() && $issue->status !== TaskStatus::Closed
    )->values();
    $groupableIssues = $issues->filter(
        fn ($issue) => $issue->isApproved() || $issue->status === TaskStatus::Closed
    );

    $groups = [];
    if ($pendingIssues->isNotEmpty()) {
        $groups[] = [
            'kind' => 'pending',
            'title' => __('issues.pending_review'),
            'headModifier' => 'progress',
            'issues' => $pendingIssues,
        ];
    }
    foreach (TaskStatus::cases() as $status) {
        $bucket = $groupableIssues->where('status', $status)->values();
        if ($bucket->isNotEmpty()) {
            $groups[] = [
                'kind' => 'status',
                'title' => __($status->labelKey()),
                'headModifier' => $status->pillModifier(),
                'issues' => $bucket,
            ];
        }
    }
@endphp

<x-wp-report-print
    :title="__('reports.issues.title')"
    :document-title="__('reports.issues.document_title')"
    :tenant="$tenant"
    :truncated="$truncated"
    :limit="$limit"
    :row-count="$issues->count()"
>
    @forelse ($groups as $group)
        <section class="wp-status-block wp-report-print__block">
            <div class="wp-group-head wp-group-head--{{ $group['headModifier'] }}">
                <h2 class="wp-group-title">{{ $group['title'] }}</h2>
                <span class="wp-group-count">{{ $group['issues']->count() }}</span>
            </div>
            <div class="wp-status-block__list">
                @foreach ($group['issues'] as $issue)
                    @include('partials.wp-issue-list-row', [
                        'issue' => $issue,
                        'highlight' => false,
                        'interactive' => false,
                    ])
                @endforeach
            </div>
        </section>
    @empty
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('reports.empty') }}</p>
        </div>
    @endforelse
</x-wp-report-print>
