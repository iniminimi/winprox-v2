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
            <h2 class="wp-section-title">{{ __('issues.show.report') }}</h2>
            @unless ($issue->isApproved())
                <button type="button" class="btn btn--warning btn--sm" wire:click="approve">{{ __('issues.approve') }}</button>
            @endunless
        </div>

        @if ($issue->reporter_name || $issue->reporter_contact)
            <p class="wp-muted">{{ $issue->reporter_name }}@if ($issue->reporter_contact) ({{ $issue->reporter_contact }})@endif</p>
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

        @include('partials.wp-issue-photo-gallery', [
            'photos' => $issue->photos->whereNull('issue_update_id'),
            'wireKeyPrefix' => 'photo',
        ])
    </div>

    <div class="wp-card wp-card-pad wp-stack">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('issues.show.tasks') }}</h2>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="openAddTaskModal">
                {{ __('issues.show.add_task_button') }}
            </button>
        </div>

        <div class="wp-list">
            @forelse ($issue->tasks as $task)
                <div class="wp-row" wire:key="task-{{ $task->id }}">
                    <div class="wp-cluster">
                        <x-wp-ref-nr type="task" :id="$task->id" />
                        <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
                        <a href="{{ route('tasks.show', $task) }}" class="wp-text-body">{{ $task->team?->name ?? __('issues.show.no_team') }}</a>
                    </div>
                    <a href="{{ route('tasks.show', $task) }}" class="btn btn--ghost btn--sm">{{ __('issues.show.manage_task_status') }}</a>
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

    <div class="wp-card wp-card-pad wp-stack">
        <h2 class="wp-section-title">{{ __('issues.show.updates') }}</h2>

        @forelse ($issue->updates as $update)
            <div class="wp-stack-tight wp-border-top" wire:key="update-{{ $update->id }}">
                <p class="wp-muted">
                    {{ $update->created_at?->format('d/m/Y H:i') }}
                    @if ($update->user)
                        — {{ $update->user->name }}
                    @elseif ($update->worker)
                        — {{ $update->worker->displayName() }}
                    @endif
                    @if ($update->kind && $update->kind !== 'note')
                        · {{ __('issues.updates.kind.'.$update->kind) }}
                    @endif
                </p>
                @if ($update->body)
                    <p class="wp-text-body">{{ $update->body }}</p>
                @endif
                @include('partials.wp-issue-photo-gallery', [
                    'photos' => $update->photos,
                    'wireKeyPrefix' => 'up-'.$update->id,
                ])
            </div>
        @empty
            <p class="wp-muted">{{ __('issues.show.updates_empty') }}</p>
        @endforelse

        <form x-data
              x-init="queueMicrotask(() => window.wpRefreshAllPhotoUploadAreas?.())"
              @submit.prevent="await window.wpAwaitPhotoUploads($el); $wire.saveUpdate()"
              class="wp-stack wp-border-top">
            <div class="wp-field">
                <label class="wp-label" for="updateBody">{{ __('issues.show.add_update') }}</label>
                <textarea id="updateBody" class="wp-textarea" wire:model="updateBody" rows="3"
                          placeholder="{{ __('issues.show.add_update_placeholder') }}"></textarea>
                @error('updateBody') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <div class="wp-field">
                <label class="wp-label">{{ __('issues.show.add_update_photos') }}</label>
                @include('partials.wp-issue-photo-upload', [
                    'model' => 'updatePhotos',
                    'removeMethod' => 'removeUpdatePhoto',
                ])
                @error('updatePhotos.*') <p class="wp-error">{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn btn--primary btn--sm" wire:loading.attr="disabled">
                {{ __('issues.show.add_update_submit') }}
            </button>
        </form>
    </div>
</div>
