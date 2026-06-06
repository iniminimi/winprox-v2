@php
    use App\Enums\IssueSource;

    $reporterName = $issue->reporter_name ?: __('issues.card.unknown_reporter');
    $reportHeading = match ($issue->source) {
        IssueSource::Qr, IssueSource::QrLocation => __('issues.show.report_reported_by', ['name' => $reporterName]),
        default => __('issues.show.report_created_by', ['name' => $reporterName]),
    };
@endphp
<div class="wp-stack">
    <x-wp-entity-detail-head
        icon="issues"
        :title="__('issues.show.overview_title')"
        help-page="issues.show"
        ref-type="issue"
        :ref-id="$issue->id"
        :headline="$headline"
        :address="$addressLine"
        route-name="issues.show"
        :current-id="$issue->id"
        :nav-label="__('issues.show.nav_label')"
        :first-id="$nav['firstId']"
        :prev-id="$nav['prevId']"
        :next-id="$nav['nextId']"
        :last-id="$nav['lastId']"
    >
        <x-slot name="meta">
            <span class="wp-pill wp-pill--{{ $issue->status->pillModifier() }}">{{ __($issue->status->labelKey()) }}</span>
            @unless ($issue->isApproved())
                <span class="wp-pill wp-pill--progress">{{ __('issues.pending_review') }}</span>
            @endunless
        </x-slot>
    </x-wp-entity-detail-head>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ $reportHeading }}</h2>
        </div>

        @if ($issue->reporter_contact)
            <p class="wp-muted">{{ $issue->reporter_contact }}</p>
        @endif

        @if ($issue->is_recurring)
            <div class="wp-card wp-card-pad wp-surface-2 wp-stack-tight">
                <div class="wp-row">
                    <div class="wp-grow wp-stack-tight">
                        <p class="wp-label">{{ __('issues.show.recurring_title') }}</p>
                        @if ($issue->recurrence_next_due_at)
                            <p class="wp-muted">{{ __('issues.show.recurring_next_due', ['date' => $issue->recurrence_next_due_at->format('d-m-Y')]) }}</p>
                        @endif
                        @if ($issue->recurrence_paused_at)
                            <span class="wp-pill wp-pill--closed">{{ __('issues.show.recurring_paused') }}</span>
                        @else
                            <span class="wp-pill wp-pill--progress">{{ __('issues.show.recurring_active') }}</span>
                        @endif
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleRecurrencePause">
                        {{ $issue->recurrence_paused_at ? __('issues.show.recurring_resume') : __('issues.show.recurring_pause') }}
                    </button>
                </div>
            </div>
        @endif

        <p class="wp-text-body">{{ $issue->description }}</p>

        @php
            $issuePhotos = $issue->photos->whereNull('issue_update_id');
        @endphp
        @if ($issuePhotos->isNotEmpty())
            <p class="wp-label">{{ __('issues.show.photos_heading') }}</p>
            @include('partials.wp-issue-photo-gallery', [
                'photos' => $issuePhotos,
                'wireKeyPrefix' => 'photo',
            ])
        @endif
    </div>

    @unless ($issue->isApproved())
        @unless ($issue->status->isClosed())
            <div class="wp-card wp-card-pad wp-stack">
                <h2 class="wp-section-title">{{ __('issues.show.status_section') }}</h2>
                <p class="wp-muted">{{ __('issues.show.status_hint') }}</p>
                <div class="wp-chip-row">
                    <button type="button" class="btn btn--warning btn--sm" wire:click="approve">{{ __('issues.approve') }}</button>
                    <button type="button" class="btn btn--danger btn--sm" wire:click="openCloseModal">{{ __('issues.close') }}</button>
                </div>
            </div>
        @endunless
    @endunless

    @if ($issue->status->isClosed() && auth()->user()->isAdmin())
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('issues.show.reopen_section') }}</h2>
            <p class="wp-muted">{{ __('issues.show.reopen_hint') }}</p>
            <div class="wp-chip-row">
                <button type="button" class="btn btn--primary btn--sm" wire:click="openReopenModal">{{ __('issues.reopen') }}</button>
            </div>
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('issues.show.tasks') }}</h2>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="openAddTaskModal">
                {{ __('issues.show.add_task_button') }}
            </button>
        </div>

        <div class="wp-list wp-list--entity-rows">
            @forelse ($issue->tasks as $task)
                @php
                    $taskDescription = trim((string) ($task->note ?: $issue->description));
                    $teamName = $task->team?->name ?? __('issues.show.no_team');
                @endphp
                <div class="wp-issue-row" wire:key="task-{{ $task->id }}">
                    <div class="wp-grow wp-stack-tight">
                        <div class="wp-cluster">
                            <x-wp-ref-nr type="task" :id="$task->id" />
                            <span class="wp-badge {{ $task->priority->badgeClass() }}">
                                <x-wp-icon :name="$task->priority->icon()" class="wp-icon wp-icon--sm" />
                                {{ $task->priority->label() }}
                            </span>
                            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                            <span class="wp-issue-card-title">{{ $teamName }}</span>
                        </div>
                        @if ($taskDescription !== '')
                            <p class="wp-issue-card-desc">{{ $taskDescription }}</p>
                        @endif
                    </div>
                    <div class="wp-stack-tight">
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditTaskModal({{ $task->id }})">
                            {{ __('common.button.edit') }}
                        </button>
                        <a href="{{ route('tasks.show', $task) }}" class="btn btn--ghost btn--sm">
                            {{ __('common.button.view') }}
                        </a>
                    </div>
                </div>
            @empty
                <p class="wp-muted">{{ __('issues.show.tasks_empty') }}</p>
            @endforelse
        </div>
    </div>

    @if ($showAddTaskModal)
        @teleport('body')
        <div class="wp-modal" role="dialog" aria-modal="true" aria-labelledby="issue-add-task-title">
            <form wire:submit="addTask" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="issue-add-task-title" class="wp-section-title">{{ __('issues.show.add_task_modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeAddTaskModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('issues.show.add_task_modal_subtitle') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="taskNote">{{ __('issues.show.task_note_label') }}</label>
                        <textarea id="taskNote" class="wp-textarea" wire:model="taskNote" rows="3"
                                  placeholder="{{ __('issues.show.task_note_placeholder') }}"></textarea>
                        @error('taskNote') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="taskScheduledFor">{{ __('issues.show.task_scheduled_label') }}</label>
                        <input type="date" id="taskScheduledFor" class="wp-input" wire:model="taskScheduledFor">
                        @error('taskScheduledFor') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="taskPriority">{{ __('tasks.show.priority') }}</label>
                        <select id="taskPriority" class="wp-select" wire:model="taskPriority">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                        @error('taskPriority') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="newTeamId">{{ __('issues.show.add_task_team_label') }}</label>
                        <select id="newTeamId" class="wp-select" wire:model="newTeamId">
                            <option value="">{{ __('issues.show.add_task_placeholder') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                        @error('newTeamId') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeAddTaskModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('issues.show.add_task_submit') }}</button>
                </div>
            </form>
        </div>
        @endteleport
    @endif

    @if ($showEditTaskModal)
        @teleport('body')
        <div class="wp-modal" role="dialog" aria-modal="true" aria-labelledby="issue-edit-task-title">
            <form wire:submit="editTask" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="issue-edit-task-title" class="wp-section-title">{{ __('issues.show.edit_task_modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeEditTaskModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('issues.show.edit_task_modal_subtitle') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="taskNote">{{ __('issues.show.task_note_label') }}</label>
                        <textarea id="taskNote" class="wp-textarea" wire:model="taskNote" rows="3"
                                  placeholder="{{ __('issues.show.task_note_placeholder') }}"></textarea>
                        @error('taskNote') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="taskScheduledFor">{{ __('issues.show.task_scheduled_label') }}</label>
                        <input type="date" id="taskScheduledFor" class="wp-input" wire:model="taskScheduledFor">
                        @error('taskScheduledFor') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="taskPriority">{{ __('tasks.show.priority') }}</label>
                        <select id="taskPriority" class="wp-select" wire:model="taskPriority">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                        @error('taskPriority') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="newTeamId">{{ __('issues.show.add_task_team_label') }}</label>
                        <select id="newTeamId" class="wp-select" wire:model="newTeamId">
                            <option value="">{{ __('issues.show.add_task_placeholder') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                        @error('newTeamId') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeEditTaskModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('issues.show.edit_task_submit') }}</button>
                </div>
            </form>
        </div>
        @endteleport
    @endif

    @if ($showCloseModal)
        @teleport('body')
        <div class="wp-modal" role="dialog" aria-modal="true" aria-labelledby="issue-close-title">
            <form wire:submit="closeIssue" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="issue-close-title" class="wp-section-title">{{ __('issues.close_modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeCloseModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('issues.close_modal_subtitle') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="closeReason">{{ __('issues.close_reason_label') }}</label>
                        <textarea id="closeReason" class="wp-textarea" wire:model="closeReason" rows="3"
                                  placeholder="{{ __('issues.close_reason_placeholder') }}"></textarea>
                        @error('closeReason') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeCloseModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--danger">{{ __('issues.close_submit') }}</button>
                </div>
            </form>
        </div>
        @endteleport
    @endif

    @if ($showReopenModal)
        @teleport('body')
        <div class="wp-modal" role="dialog" aria-modal="true" aria-labelledby="issue-reopen-title">
            <form wire:submit="reopenIssue" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="issue-reopen-title" class="wp-section-title">{{ __('issues.reopen_modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeReopenModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('issues.reopen_modal_subtitle') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="reopenReason">{{ __('issues.reopen_reason_label') }}</label>
                        <textarea id="reopenReason" class="wp-textarea" wire:model="reopenReason" rows="3"
                                  placeholder="{{ __('issues.reopen_reason_placeholder') }}"></textarea>
                        @error('reopenReason') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeReopenModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ __('issues.reopen_submit') }}</button>
                </div>
            </form>
        </div>
        @endteleport
    @endif

    @include('livewire.issues.partials.updates-section')

    @if ($showUpdateModal)
        @include('livewire.issues.partials.update-modal')
    @endif
</div>
