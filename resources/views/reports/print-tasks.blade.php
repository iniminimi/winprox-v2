@php
    use App\Enums\TaskStatus;

    $groups = [];
    foreach (TaskStatus::cases() as $status) {
        $bucket = $tasks->where('status', $status)->values();
        if ($bucket->isNotEmpty()) {
            $groups[] = [
                'status' => $status,
                'tasks' => $bucket,
            ];
        }
    }
@endphp

<x-wp-report-print
    :title="__('reports.tasks.title')"
    :document-title="__('reports.tasks.document_title')"
    :tenant="$tenant"
    :truncated="$truncated"
    :limit="$limit"
    :row-count="$tasks->count()"
>
    @forelse ($groups as $group)
        <section class="wp-status-block wp-report-print__block">
            <div class="wp-group-head wp-group-head--{{ $group['status']->pillModifier() }}">
                <h2 class="wp-group-title">{{ __($group['status']->labelKey()) }}</h2>
                <span class="wp-group-count">{{ $group['tasks']->count() }}</span>
            </div>
            <div class="wp-status-block__list">
                @foreach ($group['tasks'] as $task)
                    @php
                        $issue = $task->issue;
                        $cardTitle = collect([
                            $issue?->location?->name,
                            $issue?->unit?->localizedName(),
                            __('tasks.card.kind_nr', ['nr' => $task->id]),
                        ])->filter()->join(', ');
                        $addressLine = $issue?->location
                            ? trim(($issue->location->country_code ?: 'BE').' '.$issue->location->formattedAddress())
                            : '';
                        $teamName = $task->team?->localizedName() ?: __('tasks.card.no_team');
                        $taskLine = __('tasks.card.line_team', ['team' => $teamName]);
                        $metaParts = collect();
                        if ($issue) {
                            $metaParts->push(__('tasks.card.meta_context', ['issue_nr' => $issue->id]));
                        }
                        if ($task->scheduled_for || $task->due_at) {
                            $metaParts->push(__('tasks.card.scheduled', [
                                'date' => ($task->scheduled_for ?? $task->due_at)?->format('d/m/Y'),
                            ]));
                        }
                        $description = trim($task->displayDescription());
                    @endphp
                    <div class="wp-issue-row wp-issue-row--static">
                        <div class="wp-grow wp-stack-tight">
                            @if ($cardTitle !== '')
                                <p class="wp-issue-card-title">{{ $cardTitle }}</p>
                            @endif
                            @if ($addressLine !== '')
                                <p class="wp-issue-card-meta">{{ $addressLine }}</p>
                            @endif
                            <p class="wp-issue-card-title">{{ $taskLine }}</p>
                            @if ($metaParts->isNotEmpty())
                                <p class="wp-issue-card-meta">{{ $metaParts->join(' · ') }}</p>
                            @endif
                            @if ($description !== '')
                                <p class="wp-issue-card-desc">{{ $description }}</p>
                            @endif
                        </div>
                        <div class="wp-issue-row-meta">
                            @if ($task->isRecurring())
                                <span class="wp-pill wp-pill--done">{{ __('tasks.card.recurring') }}</span>
                            @endif
                            <span class="wp-badge {{ $task->priority->badgeClass() }}">
                                <x-wp-icon :name="$task->priority->icon()" class="wp-icon wp-icon--sm" />
                                {{ $task->priority->label() }}
                            </span>
                            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="wp-card wp-card-pad">
            <p class="wp-muted">{{ __('reports.empty') }}</p>
        </div>
    @endforelse
</x-wp-report-print>
