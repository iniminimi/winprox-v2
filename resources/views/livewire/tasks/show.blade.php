@php
    use App\Enums\IssueSource;

    $issue = $task->issue;
    $canUpdate = auth()->user()?->can('update', $task) ?? false;
    $teamName = $task->team?->name ?: __('tasks.card.no_team');
    $taskDescription = trim((string) ($task->note ?: $issue?->description));
    $issueDescriptionDiffers = $issue
        && filled($issue->description)
        && trim((string) $issue->description) !== trim((string) $task->note);
    $reporterName = $issue?->reporter_name ?: __('issues.card.unknown_reporter');
    $issueHeading = $issue ? match ($issue->source) {
        IssueSource::Qr, IssueSource::QrLocation => __('issues.show.report_reported_by', ['name' => $reporterName]),
        default => __('issues.show.report_created_by', ['name' => $reporterName]),
    } : null;
@endphp

<div class="wp-stack">
    <x-wp-entity-detail-head
        icon="tasks"
        :title="__('tasks.show.overview_title')"
        help-page="tasks.show"
        ref-type="task"
        :ref-id="$task->id"
        :headline="$headline"
        :address="$addressLine"
        route-name="tasks.show"
        :current-id="$task->id"
        :nav-label="__('tasks.show.nav_label')"
        :first-id="$nav['firstId']"
        :prev-id="$nav['prevId']"
        :next-id="$nav['nextId']"
        :last-id="$nav['lastId']"
    >
        <x-slot name="meta">
            @if ($issue)
                <a href="{{ route('issues.show', $issue) }}" class="wp-muted">{{ __('tasks.card.issue_nr', ['nr' => $issue->id]) }}</a>
            @endif
            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
        </x-slot>
    </x-wp-entity-detail-head>

    <div class="wp-card wp-card-pad wp-stack-tight">
        <h2 class="wp-section-title">{{ __('tasks.show.task_line', ['team' => $teamName]) }}</h2>
        @if ($taskDescription !== '')
            <p class="wp-text-body">{{ $taskDescription }}</p>
        @endif
        @if ($task->scheduled_for || $task->due_at)
            <p class="wp-muted">{{ __('tasks.show.due', ['date' => ($task->scheduled_for ?? $task->due_at)?->format('d/m/Y')]) }}</p>
        @endif
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('tasks.show.team_section') }}</h2>
        <p class="wp-muted">{{ __('tasks.show.team_hint') }}</p>

        @if ($task->team)
            <p class="wp-text-body">
                {{ __('tasks.show.team_current', ['name' => $task->team->name]) }}
            </p>
        @else
            <p class="wp-muted">{{ __('tasks.card.no_team') }}</p>
        @endif

        @if ($canUpdate)
            <form wire:submit="saveTeam" class="wp-stack-tight">
                <div class="wp-field">
                    <label class="wp-label" for="teamId">{{ __('tasks.show.team_select_label') }}</label>
                    <select id="teamId" class="wp-select" wire:model="teamId">
                        <option value="">{{ __('tasks.show.team_select_placeholder') }}</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}">{{ $team->name }}</option>
                        @endforeach
                    </select>
                    @error('teamId') <p class="wp-error">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="btn btn--primary">{{ __('tasks.show.team_save') }}</button>
            </form>
        @endif

    </div>

    @if ($transitions !== [])
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('tasks.show.change_status') }}</h2>
            <div class="wp-chip-row">
                @foreach ($transitions as $status)
                    <button type="button"
                            class="btn btn--ghost btn--sm {{ $targetStatus === $status->value ? 'is-active' : '' }}"
                            wire:click="selectStatus('{{ $status->value }}')">
                        {{ __($status->labelKey()) }}
                    </button>
                @endforeach
            </div>

            @if ($targetStatus !== '')
                @if ($requiresReason)
                    <div class="wp-field">
                        <label class="wp-label" for="reason">{{ __('tasks.show.reason') }}</label>
                        <textarea id="reason" class="wp-textarea" wire:model="reason" rows="3"></textarea>
                        @error('reason') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <button type="button" class="btn btn--primary" wire:click="updateStatus">{{ __('tasks.show.confirm_status') }}</button>
            @endif

            @if ($task->status === \App\Enums\TaskStatus::InProgress)
                <div class="wp-field wp-stack-tight wp-border-top">
                    <label class="wp-label" for="pauseNote">{{ __('tasks.show.pause') }}</label>
                    <textarea id="pauseNote" class="wp-textarea" wire:model="pauseNote" rows="2"></textarea>
                    @error('pauseNote') <p class="wp-error">{{ $message }}</p> @enderror
                    <button type="button" class="btn btn--ghost" wire:click="pause">{{ __('tasks.show.pause_submit') }}</button>
                </div>
            @endif
        </div>
    @endif

    @if ($issue)
        <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-row">
                <h2 class="wp-section-title">{{ $issueHeading }}</h2>
                <a href="{{ route('issues.show', $issue) }}" class="btn btn--ghost btn--sm">{{ __('tasks.show.view_issue') }}</a>
            </div>
            @if ($issue->reporter_contact)
                <p class="wp-muted">{{ $issue->reporter_contact }}</p>
            @endif
            @if ($issueDescriptionDiffers)
                <p class="wp-text-body">{{ $issue->description }}</p>
            @endif
        </div>
    @endif

    @if ($issue?->updates->isNotEmpty())
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('tasks.show.updates') }}</h2>
            @foreach ($issue->updates->sortByDesc('created_at') as $update)
                <div class="wp-stack-tight" wire:key="update-{{ $update->id }}">
                    <p class="wp-muted">{{ optional($update->created_at)->format('d/m/Y H:i') }}
                        @if ($update->user) — {{ $update->user->name }} @endif
                    </p>
                    <p>{{ $update->body }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
