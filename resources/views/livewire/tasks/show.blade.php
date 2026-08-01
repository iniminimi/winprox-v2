@php
    use App\Enums\IssueSource;

    $issue = $task->issue;
    $canUpdate = auth()->user()?->can('update', $task) ?? false;
    $teamName = $task->team?->localizedName() ?: __('tasks.card.no_team');
    $taskDescription = trim($task->displayDescription());
    $issueDescriptionDiffers = $issue
        && filled($issue->localizedDescription())
        && filled(trim((string) ($task->description ?? '')))
        && trim((string) $issue->localizedDescription()) !== trim((string) $task->description);
    $reporterName = $issue?->reporter_name ?: __('issues.card.unknown_reporter');
    $issueHeading = $issue ? match ($issue->source) {
        IssueSource::Qr => __('issues.show.report_reported_by', ['name' => $reporterName]),
        default => __('issues.show.report_created_by', ['name' => $reporterName]),
    } : null;
@endphp

<div class="wp-stack" data-manual-capture="tasks-show">
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
            @if ($task->isRecurring())
                <span class="wp-pill wp-pill--done">{{ __('tasks.card.recurring') }}</span>
            @endif
            <span class="wp-badge {{ $task->priority->badgeClass() }}">
                <x-wp-icon :name="$task->priority->icon()" class="wp-icon wp-icon--sm" />
                {{ $task->priority->label() }}
            </span>
            <span class="wp-pill wp-pill--{{ $task->status->pillModifier() }}">{{ __($task->status->labelKey()) }}</span>
        </x-slot>
    </x-wp-entity-detail-head>

    @if ($task->isRecurring())
        <div class="wp-card wp-card-pad wp-stack-tight">
            <p class="wp-section-title">{{ __('tasks.show.recurring_title') }}</p>
            @if ($task->is_recurring_cycle)
                <p class="wp-muted">{{ __('tasks.show.recurring_cycle', ['nr' => $task->cycle_number ?? 1]) }}</p>
            @endif
            @if ($issue?->recurrence_interval_value && $issue?->recurrence_interval_unit)
                <p class="wp-muted">{{ __('tasks.show.recurring_interval', [
                    'value' => $issue->recurrence_interval_value,
                    'unit' => __('issues.create.unit_'.$issue->recurrence_interval_unit->value),
                ]) }}</p>
            @endif
            @if ($issue?->recurrence_next_due_at)
                <p class="wp-muted">{{ __('tasks.show.recurring_next_due', ['date' => $issue->recurrence_next_due_at->format('d-m-Y')]) }}</p>
            @endif
        </div>
    @endif

    <div class="wp-card wp-card-pad wp-stack-tight">
        <div class="wp-row">
            <h2 class="wp-section-title">{{ __('tasks.show.task_line', ['team' => $teamName]) }}</h2>
            @if ($canUpdate)
                <button type="button" class="btn btn--ghost btn--sm" wire:click="openEditTaskModal">
                    {{ __('issues.show.edit_task_modal_title') }}
                </button>
            @endif
        </div>
        @if ($taskDescription !== '')
            <p class="wp-text-body">{{ $taskDescription }}</p>
        @endif
        @if ($task->scheduled_for || $task->due_at)
            <p class="wp-muted">{{ __('tasks.show.due', ['date' => ($task->scheduled_for ?? $task->due_at)?->format('d/m/Y')]) }}</p>
        @endif
        <p class="wp-text-body">{{ $task->priority->label() }}</p>
    </div>

    @if ($esgChainSteps !== [])
        <div class="wp-card wp-card-pad wp-stack-tight">
            <div class="wp-stack-tight">
                <p class="wp-section-title">{{ __('esg.chain.title') }}</p>
                <p class="wp-muted wp-text-sm">{{ __('esg.chain.subtitle') }}</p>
            </div>
            @include('partials.wp-esg-operation-chain', ['steps' => $esgChainSteps])
        </div>
    @endif

    @if ($showEditTaskModal)
        @teleport('body')
        <div class="wp-modal" role="dialog" aria-modal="true" aria-labelledby="task-edit-title" x-on:keydown.escape.window="$wire.closeEditTaskModal()">
            <form wire:submit="saveDetails" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="task-edit-title" class="wp-section-title">{{ __('issues.show.edit_task_modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeEditTaskModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('issues.show.edit_task_modal_subtitle') }}</p>
                    @include('partials.wp-edit-task-recurring-hint', ['issue' => $issue])
                    <div class="wp-field">
                        <label class="wp-label" for="taskNote">{{ __('issues.show.task_note_label') }}</label>
                        <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                            <textarea id="taskNote" class="wp-textarea" wire:model="taskNote" rows="3"
                                      placeholder="{{ __('issues.show.task_note_placeholder') }}"
                                      maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                                      x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                            <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                        </div>
                        @error('taskNote') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>

                    @if (filled(trim((string) ($task->description ?? ''))))
                        <div class="wp-field" x-data="{ open: false }">
                            <span class="wp-label">{{ __('tasks.translation_edit.label') }}</span>

                            <div class="wp-field-panel" :class="{ 'is-open': open }">
                                <button
                                    type="button"
                                    class="wp-field-panel__trigger"
                                    @click="open = !open"
                                    :aria-expanded="open"
                                >
                                    <span>{{ __('tasks.translation_edit.open') }}</span>
                                    <x-wp-icon name="chevron-down" class="wp-disclosure-chevron" x-bind:class="{ 'is-open': open }" />
                                </button>

                                <div class="wp-field-panel__body wp-stack-tight" x-show="open" x-cloak>
                                    <div class="wp-cluster wp-issue-description-row">
                                        <select
                                            class="wp-select wp-select--compact"
                                            wire:model.live="taskPreviewLocale"
                                            aria-label="{{ __('issues.show.description_language') }}"
                                        >
                                            @foreach ($taskTranslationLocales as $code => $label)
                                                <option value="{{ $code }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <label class="wp-field">
                                        <span class="wp-label">{{ __('tasks.translation_edit.description') }}</span>
                                        <textarea class="wp-textarea" wire:model="taskTranslationDescription" rows="3"></textarea>
                                        @error('taskTranslationDescription') <span class="wp-error">{{ $message }}</span> @enderror
                                    </label>

                                    <div class="wp-row">
                                        <button
                                            type="button"
                                            class="btn btn--ghost btn--sm"
                                            wire:click="saveTaskTranslationOverride"
                                            wire:loading.attr="disabled"
                                            wire:target="saveTaskTranslationOverride"
                                        >
                                            <span wire:loading wire:target="saveTaskTranslationOverride" class="wp-mr-2">
                                                <x-wp-spinner size="sm" />
                                            </span>
                                            <span>{{ __('tasks.translation_edit.save') }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="wp-field">
                        <label class="wp-label" for="taskScheduledFor">{{ __('issues.show.task_scheduled_label') }}</label>
                        <input type="date" id="taskScheduledFor" class="wp-input" wire:model="taskScheduledFor">
                        @error('taskScheduledFor') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="priority">{{ __('tasks.show.priority') }}</label>
                        <select id="priority" class="wp-select" wire:model="priority">
                            @foreach ($priorities as $prio)
                                <option value="{{ $prio->value }}">{{ $prio->label() }}</option>
                            @endforeach
                        </select>
                        @error('priority') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="wp-field">
                        <label class="wp-label" for="teamId">{{ __('issues.show.add_task_team_label') }}</label>
                        <select id="teamId" class="wp-select" wire:model="teamId">
                            <option value="">{{ __('issues.show.add_task_placeholder') }}</option>
                            @foreach ($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
                            @endforeach
                        </select>
                        @error('teamId') <p class="wp-error">{{ $message }}</p> @enderror
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

    @if ($transitions !== [])
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('tasks.show.change_status') }}</h2>
            <div class="wp-chip-row">
                @foreach ($transitions as $status)
                    @php
                        $buttonClass = match($status->value) {
                            'in_progress' => 'btn--primary',
                            'done' => 'btn--success',
                            'closed' => 'btn--danger',
                            'new' => 'btn--warning',
                            default => 'btn--ghost',
                        };
                    @endphp
                    <button type="button"
                            class="btn {{ $buttonClass }} btn--sm {{ $targetStatus === $status->value ? 'is-active' : '' }}"
                            wire:click="selectStatus('{{ $status->value }}')">
                        {{ __($status->labelKey()) }}
                    </button>
                @endforeach
            </div>

            @if ($targetStatus !== '')
                @if ($requiresReason)
                    <div class="wp-field">
                        <label class="wp-label" for="reason">{{ __('tasks.show.reason') }}</label>
                        <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                            <textarea id="reason" class="wp-textarea" wire:model="reason" rows="3"
                                      maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                                      x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                            <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                        </div>
                        @error('reason') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <button type="button" class="btn btn--primary" wire:click="updateStatus">{{ __('tasks.show.confirm_status') }}</button>
            @endif

            @if ($task->status === \App\Enums\TaskStatus::InProgress)
                <div class="wp-field wp-stack-tight wp-border-top">
                    <label class="wp-label" for="pauseNote">{{ __('tasks.show.pause') }}</label>
                    <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                        <textarea id="pauseNote" class="wp-textarea" wire:model="pauseNote" rows="2"
                                  maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                                  x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                        <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                    </div>
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
                <p class="wp-text-body">{{ $issue->localizedDescription() }}</p>
            @endif
        </div>
    @endif

    @if ($task->updates->isNotEmpty())
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('tasks.show.updates') }}</h2>
            <p class="wp-muted wp-text-sm">{{ __('tasks.show.updates_hint') }}</p>
            @foreach ($task->updates->sortByDesc('created_at') as $update)
                <div class="wp-card wp-card-pad wp-stack-tight wp-surface-muted" wire:key="task-update-{{ $update->id }}">
                    <div class="wp-row">
                        <span aria-hidden="true"></span>
                        <p class="wp-muted wp-text-sm">{{ $update->created_at?->format('d/m/Y H:i') }}</p>
                    </div>

                    @if ($update->worker)
                        <p class="wp-muted wp-text-sm">
                            {{ __('issues.show.added_by_worker') }} {{ $update->worker->displayName() }}
                        </p>
                    @elseif ($update->user)
                        <p class="wp-muted wp-text-sm">
                            {{ __('issues.show.added_by') }} {{ $update->user->name }}
                        </p>
                    @endif

                    @if (filled($update->description))
                        <p class="wp-text-body">{{ $update->description }}</p>
                    @elseif ($update->kind && $update->kind !== 'note')
                        <p class="wp-text-body">{{ __('issues.updates.kind.'.$update->kind) }}</p>
                    @endif

                    @include('partials.wp-issue-photo-gallery', [
                        'photos' => $update->photos,
                        'wireKeyPrefix' => 'task-up-'.$update->id,
                    ])
                </div>
            @endforeach
        </div>
    @endif
</div>
