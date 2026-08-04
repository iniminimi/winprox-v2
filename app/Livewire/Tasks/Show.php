<?php

namespace App\Livewire\Tasks;

use App\Actions\Communication\ImportTaskTranslationsAction;
use App\Actions\Tasks\PauseTaskAction;
use App\Actions\Tasks\RoundTaskCompletionAction;
use App\Actions\Tasks\UpdateTaskDetailsAction;
use App\Actions\Tasks\UpdateTaskPriorityAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Actions\Tasks\UpdateTaskTeamAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Task;
use App\Support\EntityDetailNavigation;
use App\Support\Esg\EsgOperationChainPresenter;
use App\Support\Tasks\TaskStatusTransitions;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Show extends Component
{
    public Task $task;

    public string $targetStatus = '';

    public string $reason = '';

    public string $pauseNote = '';

    public ?int $teamId = null;

    public string $priority = '';

    public string $taskNote = '';

    public ?string $taskScheduledFor = null;

    public bool $showEditTaskModal = false;

    public string $taskPreviewLocale = '';

    public string $taskTranslationDescription = '';

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->task = $task->load([
            'issue.location',
            'issue.unit.translations',
            'issue.roundStops.unit.translations',
            'issue.esgIndicator.translations',
            'issue.translations',
            'updates.user',
            'updates.worker',
            'updates.photos',
            'translations',
            'team.translations',
            'roundStopSkips',
            'esgThresholdMeasurement.indicator.translations',
            'esgThresholdMeasurement.task',
            'esgThresholdMeasurement.thresholdFollowUpTask',
        ]);
        $this->syncFormFromTask();
    }

    public function openEditTaskModal(): void
    {
        $this->authorize('update', $this->task);
        $this->syncFormFromTask();
        $this->task->loadMissing('translations');
        $this->taskPreviewLocale = $this->defaultTranslationLocaleForTask($this->task);
        $this->hydrateTaskTranslationInput($this->task);
        $this->resetValidation();
        $this->showEditTaskModal = true;
    }

    public function closeEditTaskModal(): void
    {
        $this->showEditTaskModal = false;
        $this->taskPreviewLocale = '';
        $this->taskTranslationDescription = '';
    }

    public function updatedTaskPreviewLocale(): void
    {
        $this->task->loadMissing('translations');
        $this->hydrateTaskTranslationInput($this->task);
    }

    public function saveTaskTranslationOverride(ImportTaskTranslationsAction $importTaskTranslations): void
    {
        $this->authorize('update', $this->task);

        if (! filled(trim((string) ($this->task->description ?? '')))) {
            $this->addError('taskTranslationDescription', __('tasks.errors.translation_requires_description'));

            return;
        }

        $validated = $this->validate([
            'taskTranslationDescription' => ['required', 'string', 'max:'.TextDescriptionLimits::TRANSLATION_MAX],
        ]);

        $locale = LocaleSupport::normalize($this->taskPreviewLocale);
        if ($locale === $this->task->normalizedOriginalLanguage()) {
            $this->addError('taskTranslationDescription', __('issues.errors.translation_same_as_source'));

            return;
        }

        $description = trim((string) $validated['taskTranslationDescription']);
        if ($description === '') {
            $this->addError('taskTranslationDescription', __('issues.errors.translation_import_invalid'));

            return;
        }

        try {
            $importTaskTranslations->handle([
                [
                    'task_id' => $this->task->id,
                    'locale' => $locale,
                    'description' => $description,
                ],
            ], (int) auth()->id());
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                if (! is_array($messages)) {
                    continue;
                }

                foreach ($messages as $message) {
                    $this->addError('taskTranslationDescription', (string) $message);
                }
            }

            return;
        }

        $this->task = $this->task->fresh([
            'issue.location',
            'issue.unit.translations',
            'issue.esgIndicator.translations',
            'issue.translations',
            'updates.user',
            'updates.worker',
            'updates.photos',
            'translations',
            'team.translations',
            'esgThresholdMeasurement.indicator.translations',
            'esgThresholdMeasurement.task',
            'esgThresholdMeasurement.thresholdFollowUpTask',
        ]);
        $this->hydrateTaskTranslationInput($this->task);
        session()->flash('success', __('tasks.flash.translation_saved'));
    }

    public function saveDetails(
        UpdateTaskPriorityAction $updatePriority,
        UpdateTaskTeamAction $updateTeam,
        UpdateTaskDetailsAction $updateDetails,
    ): void {
        $this->authorize('update', $this->task);

        $this->taskNote = trim($this->taskNote);

        $validated = $this->validate([
            'teamId' => ['required', 'integer', 'exists:internal_teams,id'],
            'taskNote' => ['required', 'string', 'min:2', 'max:'.TextDescriptionLimits::MAX],
            'taskScheduledFor' => ['nullable', 'date'],
            'priority' => ['required', 'string', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
        ], [
            'teamId.required' => __('tasks.show.errors.team_required'),
            'taskNote.required' => __('issues.show.errors.task_note_required'),
            'taskNote.min' => __('issues.show.errors.task_note_min'),
            'taskNote.max' => __('issues.errors.text_max'),
        ]);

        $updatePriority->handle(
            $this->task,
            TaskPriority::from($validated['priority']),
            auth()->user()->tenant_id,
            auth()->id(),
        );

        if ($this->task->internal_team_id !== (int) $validated['teamId']) {
            $updateTeam->handle($this->task, (int) $validated['teamId']);
        }

        $updateDetails->handle(
            $this->task,
            $validated['taskNote'],
            $validated['taskScheduledFor'] ?? null,
            auth()->user()->tenant_id,
            auth()->id(),
        );

        $this->closeEditTaskModal();
        $this->refreshTask();
    }

    public function selectStatus(string $status): void
    {
        $this->targetStatus = $status;
        $this->reason = '';
    }

    public function updateStatus(UpdateTaskStatusAction $updateStatus): void
    {
        $this->authorize('update', $this->task);

        $to = TaskStatus::from($this->targetStatus);
        $from = $this->task->status instanceof TaskStatus
            ? $this->task->status
            : TaskStatus::from((string) $this->task->status);

        $rules = [
            'targetStatus' => ['required', 'in:'.implode(',', array_column(TaskStatus::cases(), 'value'))],
        ];

        if (TaskStatusTransitions::requiresReason($from, $to)) {
            $rules['reason'] = ['required', 'string', 'max:'.TextDescriptionLimits::MAX];
        }

        $validated = $this->validate($rules, [
            'reason.required' => __('tasks.errors.reason_required'),
            'reason.max' => __('issues.errors.text_max'),
        ]);

        $updateStatus->handle(
            $this->task,
            TaskStatus::from($validated['targetStatus']),
            auth()->user(),
            $validated['reason'] ?? null,
        );

        $this->reset(['targetStatus', 'reason']);
        $this->refreshTask();
    }

    public function pause(PauseTaskAction $pause): void
    {
        $this->authorize('update', $this->task);

        $this->validate([
            'pauseNote' => ['required', 'string', 'max:'.TextDescriptionLimits::MAX],
        ], [
            'pauseNote.max' => __('issues.errors.text_max'),
        ]);

        $pause->handle($this->task, $this->pauseNote, auth()->user());
        $this->pauseNote = '';
        $this->refreshTask();
    }

    protected function refreshTask(): void
    {
        $this->task = $this->task->fresh([
            'issue.location',
            'issue.unit',
            'issue.roundStops.unit.translations',
            'updates.user',
            'updates.worker',
            'updates.photos',
            'team.translations',
            'roundStopSkips',
        ]);
        $this->syncFormFromTask();
    }

    protected function syncFormFromTask(): void
    {
        $this->teamId = $this->task->internal_team_id;
        $this->priority = $this->task->priority instanceof TaskPriority
            ? $this->task->priority->value
            : (string) $this->task->priority;
        $this->taskNote = trim((string) ($this->task->description ?: $this->task->issue?->description));
        $this->taskScheduledFor = $this->task->scheduled_for?->format('Y-m-d');
    }

    private function hydrateTaskTranslationInput(Task $task): void
    {
        $locale = LocaleSupport::normalize($this->taskPreviewLocale);
        if ($locale === $task->normalizedOriginalLanguage()) {
            $locale = $this->defaultTranslationLocaleForTask($task);
            $this->taskPreviewLocale = $locale;
        }

        $translation = $task->translations
            ->first(fn ($row) => $row->locale === $locale);

        $this->taskTranslationDescription = (string) ($translation?->description ?? '');
    }

    private function defaultTranslationLocaleForTask(Task $task): string
    {
        $targets = LocaleSupport::targetLocalesForSource($task->normalizedOriginalLanguage());
        $preferred = LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale());

        if (in_array($preferred, $targets, true)) {
            return $preferred;
        }

        return $targets[0] ?? $preferred;
    }

    public function render()
    {
        $current = $this->task->status instanceof TaskStatus
            ? $this->task->status
            : TaskStatus::from((string) $this->task->status);

        $target = TaskStatus::tryFrom($this->targetStatus);

        $issue = $this->task->issue;
        $location = $issue?->location;
        if ($issue?->isInspectionRound()) {
            $headline = collect([
                $location?->localizedName(),
                __('issues.card.round_stops', ['count' => $issue->roundStopCount()]),
            ])->filter()->join(' · ');
        } else {
            $headline = collect([$location?->localizedName(), $issue?->unit?->localizedName()])->filter()->join(' · ');
        }
        if ($headline === '' && $issue) {
            $headline = \Illuminate\Support\Str::limit($issue->localizedDescription(), 80);
        }
        $addressLine = $location
            ? trim(($location->country_code ?: 'BE').' '.$location->formattedAddress())
            : '';

        $roundProgress = null;
        if ($issue?->isInspectionRound()) {
            $roundProgress = app(RoundTaskCompletionAction::class)->progress($this->task);
        }

        $taskTranslationLocales = config('locales.labels', []);
        if ($this->showEditTaskModal) {
            $sourceLocale = $this->task->normalizedOriginalLanguage();
            $taskTranslationLocales = array_filter(
                $taskTranslationLocales,
                fn (string $label, string $code): bool => $code !== $sourceLocale,
                ARRAY_FILTER_USE_BOTH,
            );
        }

        return view('livewire.tasks.show', [
            'task' => $this->task,
            'headline' => $headline,
            'addressLine' => $addressLine,
            'teams' => InternalTeam::query()
                ->where('is_active', true)
                ->with('translations')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'original_language']),
            'priorities' => TaskPriority::cases(),
            'transitions' => TaskStatusTransitions::nextOptions($current),
            'requiresReason' => $target !== null && TaskStatusTransitions::requiresReason($current, $target),
            'nav' => EntityDetailNavigation::forTask($this->task),
            'esgChainSteps' => EsgOperationChainPresenter::stepsForTask($this->task),
            'taskTranslationLocales' => $taskTranslationLocales,
            'roundProgress' => $roundProgress,
        ]);
    }
}
