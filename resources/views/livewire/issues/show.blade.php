@php
    use App\Enums\IssueSource;

    $reporterName = $issue->reporter_name ?: __('issues.card.unknown_reporter');
    $reportHeading = match ($issue->source) {
        IssueSource::Qr => __('issues.show.report_reported_by', ['name' => $reporterName]),
        default => __('issues.show.report_created_by', ['name' => $reporterName]),
    };
@endphp
<div class="wp-stack" data-manual-capture="issues-show">
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
                        @if (! $issue->recurrence_active)
                            <span class="wp-pill wp-pill--closed">{{ __(\App\Enums\TaskStatus::Closed->labelKey()) }}</span>
                        @elseif ($issue->recurrence_paused_at)
                            <span class="wp-pill wp-pill--closed">{{ __('issues.show.recurring_paused') }}</span>
                        @else
                            <span class="wp-pill wp-pill--progress">{{ __('issues.show.recurring_active') }}</span>
                        @endif
                        @if ($issue->esgIndicator)
                            <p class="wp-muted">{{ __('issues.show.esg_indicator', ['name' => $issue->esgIndicator->localizedName()]) }}</p>
                        @endif
                        @if ($issue->isInspectionRound())
                            <span class="wp-pill wp-pill--progress">{{ __('issues.card.round_stops', ['count' => $issue->roundStopCount()]) }}</span>
                        @endif
                    </div>
                    @can('update', $issue)
                        @if ($issue->recurrence_active)
                            <div class="wp-chip-row">
                                <button type="button" class="btn btn--ghost btn--sm" wire:click="toggleRecurrencePause">
                                    {{ $issue->recurrence_paused_at ? __('issues.show.recurring_resume') : __('issues.show.recurring_pause') }}
                                </button>
                                <button type="button" class="btn btn--danger btn--sm" wire:click="openEndRecurringModal">
                                    {{ __(\App\Enums\TaskStatus::Closed->labelKey()) }}
                                </button>
                            </div>
                        @endif
                    @endcan
                </div>

                @can('update', $issue)
                    <div class="wp-field">
                        <label class="wp-label" @if($roundStopUnitsGrouped->flatten(1)->isNotEmpty()) for="show_round_stop_unit_ids" @endif>{{ __('issues.show.round_stops') }}</label>
                        @if ($issue->roundStops->isNotEmpty())
                            <ol class="wp-round-stops">
                                @foreach ($issue->roundStops->sortBy('sort_order')->values() as $index => $stop)
                                    @php
                                        $stopUnitName = $stop->unit?->localizedName() ?? ('#'.$stop->unit_id);
                                        $stopLocationName = $stop->unit?->location?->name
                                            ?: ($stop->unit?->location?->address ?? null);
                                        $stopLabel = $roundStopsMultiLocation && $stopLocationName
                                            ? $stopLocationName.' · '.$stopUnitName
                                            : $stopUnitName;
                                    @endphp
                                    <li class="wp-round-stops__item">
                                        <span class="wp-round-stops__index">{{ $index + 1 }}</span>
                                        <span class="wp-round-stops__name">{{ $stopLabel }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                        @if ($roundStopUnitsGrouped->flatten(1)->isEmpty())
                            <p class="wp-muted wp-text-sm">
                                @if ($roundStopUnitsHiddenCount > 0)
                                    {{ trans_choice('issues.create.round_stops_unavailable', $roundStopUnitsHiddenCount, ['count' => $roundStopUnitsHiddenCount]) }}
                                @else
                                    {{ __('issues.create.round_stops_empty') }}
                                @endif
                            </p>
                        @else
                            <p class="wp-muted wp-text-sm">{{ __('issues.show.round_stops_help') }}</p>
                            <div
                                x-data="{
                                    toggleAll(event) {
                                        const checked = !!event.target.checked;
                                        this.$root.querySelectorAll('input[type=checkbox][data-round-stop]:not(:disabled)').forEach((box) => {
                                            if (box.checked !== checked) {
                                                box.checked = checked;
                                                box.dispatchEvent(new Event('change', { bubbles: true }));
                                            }
                                        });
                                    }
                                }"
                                class="wp-stack-tight"
                            >
                            <label class="wp-check wp-text-sm">
                                <input type="checkbox" @change="toggleAll($event)">
                                {{ __('issues.create.round_stops_select_all') }}
                            </label>
                            <div id="show_round_stop_unit_ids" class="wp-round-stop-picker">
                                @foreach ($roundStopUnitsGrouped as $locationUnits)
                                    @php
                                        $groupLocation = $locationUnits->first()?->location;
                                        $groupLabel = $groupLocation?->name ?: ($groupLocation?->address ?? __('issues.create.location_none'));
                                    @endphp
                                    <div class="wp-round-stop-picker__group" role="group" aria-label="{{ $groupLabel }}">
                                        <p class="wp-round-stop-picker__group-label">{{ $groupLabel }}</p>
                                        @foreach ($locationUnits as $unit)
                                            <label class="wp-round-stop-picker__row">
                                                <input
                                                    type="checkbox"
                                                    value="{{ $unit->id }}"
                                                    wire:model="round_stop_unit_ids"
                                                    data-round-stop
                                                >
                                                <span>{{ $unit->localizedName() }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            </div>
                            @if ($roundStopUnitsHiddenCount > 0)
                                <p class="wp-muted wp-text-sm">{{ trans_choice('issues.create.round_stops_hidden', $roundStopUnitsHiddenCount, ['count' => $roundStopUnitsHiddenCount]) }}</p>
                            @endif
                            @error('round_stop_unit_ids') <p class="wp-error">{{ $message }}</p> @enderror
                            @error('round_stop_unit_ids.*') <p class="wp-error">{{ $message }}</p> @enderror
                            <button type="button" class="btn btn--primary btn--sm" wire:click="saveRoundStops">
                                {{ __('issues.show.round_stops_save') }}
                            </button>
                        @endif
                    </div>
                @endcan
            </div>
        @endif

        @if ($issue->isApproved())
            <div class="wp-cluster wp-issue-description-row">
                <select
                    id="descriptionLocale"
                    class="wp-select"
                    wire:model.live="descriptionLocale"
                    aria-label="{{ __('issues.show.description_language') }}"
                >
                    @foreach ($descriptionLocales as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
                <span @class(['wp-text-body', 'wp-issue-description-text', 'wp-muted' => $descriptionMissing])>{{ $descriptionText }}</span>
            </div>
        @else
            <p class="wp-text-body">{{ $issue->description }}</p>
        @endif

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
        @unless ($issue->status === \App\Enums\TaskStatus::Closed)
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

    @can('update', $issue)
    @if ($issue->status === \App\Enums\TaskStatus::Closed)
        <div class="wp-card wp-card-pad wp-stack">
            <h2 class="wp-section-title">{{ __('issues.show.reopen_section') }}</h2>
            <p class="wp-muted">{{ __('issues.show.reopen_hint') }}</p>
            <div class="wp-chip-row">
                <button type="button" class="btn btn--primary btn--sm" wire:click="openReopenModal">{{ __('issues.reopen') }}</button>
            </div>
        </div>
    @endif
    @endcan

    <div class="wp-card wp-card-pad wp-stack">
        @if ($issue->isApproved())
            <div class="wp-row">
                <h2 class="wp-section-title">{{ __('issues.show.tasks') }}</h2>
                <button type="button" class="btn btn--ghost btn--sm" wire:click="openAddTaskModal">
                    {{ __('issues.show.add_task_button') }}
                </button>
            </div>

            <div class="wp-list wp-list--entity-rows">
                @forelse ($issue->tasks as $task)
                    @php
                        $taskDescription = trim($task->displayDescription());
                        $teamName = $task->team?->localizedName() ?? __('issues.show.no_team');
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
                                @if ($task->isRecurring())
                                    <span class="wp-pill wp-pill--done">{{ __('tasks.card.recurring') }}</span>
                                @endif
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
        @else
            <h2 class="wp-section-title">{{ __('issues.show.tasks') }}</h2>
            <p class="wp-muted">{{ __('issues.show.tasks_hidden_until_approved') }}</p>
        @endif
    </div>

    @if ($showAddTaskModal && $issue->isApproved())
        @teleport('body')
        <div class="wp-modal" role="dialog" aria-modal="true" aria-labelledby="issue-add-task-title" x-on:keydown.escape.window="$wire.closeAddTaskModal()">
            <form wire:submit="addTask" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="issue-add-task-title" class="wp-section-title">{{ __('issues.show.add_task_modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeAddTaskModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('issues.show.add_task_modal_subtitle') }}</p>
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
                    <div class="wp-field">
                        <label class="wp-label" for="taskScheduledFor">{{ __('issues.show.task_scheduled_label') }}</label>
                        <x-wp-date-input id="taskScheduledFor" wire:model="taskScheduledFor" />
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
                                <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
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

    @if ($showEditTaskModal && $issue->isApproved())
        @teleport('body')
        <div class="wp-modal" role="dialog" aria-modal="true" aria-labelledby="issue-edit-task-title" x-on:keydown.escape.window="$wire.closeEditTaskModal()">
            <form wire:submit="editTask" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="issue-edit-task-title" class="wp-section-title">{{ __('issues.show.edit_task_modal_title') }}</h2>
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

                    @if ($editTask && filled(trim((string) ($editTask->description ?? ''))))
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
                        <x-wp-date-input id="taskScheduledFor" wire:model="taskScheduledFor" />
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
                                <option value="{{ $team->id }}">{{ $team->localizedName() }}</option>
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
                        <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                            <textarea id="closeReason" class="wp-textarea" wire:model="closeReason" rows="3"
                                      placeholder="{{ __('issues.close_reason_placeholder') }}"
                                      maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                                      x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                            <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                        </div>
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

    @if ($showEndRecurringModal)
        @teleport('body')
        <x-wp-modal closeMethod="closeEndRecurringModal" aria-labelledby="issue-end-recurring-title">
            <form wire:submit="endRecurringIssue" class="wp-card wp-modal-card wp-modal-card--form">
                <div class="wp-modal-head wp-modal-head--bordered">
                    <h2 id="issue-end-recurring-title" class="wp-section-title">{{ __('issues.close_modal_title') }}</h2>
                    <x-wp-modal-close wire:click="closeEndRecurringModal" />
                </div>
                <div class="wp-modal-body wp-stack">
                    <p class="wp-muted">{{ __('issues.show.recurring_end_modal_subtitle') }}</p>
                    <div class="wp-field">
                        <label class="wp-label" for="endReason">{{ __('issues.close_reason_label') }}</label>
                        <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                            <textarea id="endReason" class="wp-textarea" wire:model="endReason" rows="3"
                                      placeholder="{{ __('issues.close_reason_placeholder') }}"
                                      maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                                      x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                            <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                        </div>
                        @error('endReason') <p class="wp-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="wp-modal-foot">
                    <button type="button" class="btn btn--ghost" wire:click="closeEndRecurringModal">{{ __('common.button.cancel') }}</button>
                    <button type="submit" class="btn btn--danger">{{ __(\App\Enums\TaskStatus::Closed->labelKey()) }}</button>
                </div>
            </form>
        </x-wp-modal>
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
                        <div x-data="{ n: 0, max: {{ \App\Support\Validation\TextDescriptionLimits::MAX }} }">
                            <textarea id="reopenReason" class="wp-textarea" wire:model="reopenReason" rows="3"
                                      placeholder="{{ __('issues.reopen_reason_placeholder') }}"
                                      maxlength="{{ \App\Support\Validation\TextDescriptionLimits::MAX }}"
                                      x-init="n = $el.value.length" x-on:input="n = $el.value.length"></textarea>
                            <p class="wp-char-counter" :class="{ 'wp-char-counter--near': n >= max - 50, 'wp-char-counter--full': n >= max }"><span x-text="n"></span>/<span x-text="max"></span></p>
                        </div>
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

    @if ($issue->isApproved())
        @include('livewire.issues.partials.updates-section')
    @endif

    @if ($showUpdateModal)
        @include('livewire.issues.partials.update-modal')
    @endif
</div>
