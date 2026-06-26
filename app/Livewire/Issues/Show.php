<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CloseIssueAction;
use App\Actions\Issues\CreateIssueUpdateAction;
use App\Actions\Issues\ReopenIssueAction;
use App\Actions\Issues\ToggleIssueRecurrencePauseAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskDetailsAction;
use App\Actions\Tasks\UpdateTaskPriorityAction;
use App\Actions\Tasks\UpdateTaskTeamAction;
use App\Enums\TaskPriority;
use App\Models\Task;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Support\EntityDetailNavigation;
use App\Support\Translation\LocaleSupport;
use App\Support\Validation\TextDescriptionLimits;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Show extends Component
{
    use WithFileUploads;

    public Issue $issue;

    public bool $showAddTaskModal = false;

    public bool $showEditTaskModal = false;

    public bool $showCloseModal = false;

    public bool $showReopenModal = false;

    public bool $showUpdateModal = false;

    public ?int $editTaskId = null;

    public ?int $newTeamId = null;

    public string $taskNote = '';

    public ?string $taskScheduledFor = null;

    public string $taskPriority = 'medium';

    public string $updateDescription = '';

    public string $closeReason = '';

    public string $reopenReason = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $updatePhotos = [];

    public string $descriptionLocale = '';

    public function mount(Issue $issue): void
    {
        $this->authorize('view', $issue);
        $this->issue = $issue;
        $this->descriptionLocale = LocaleSupport::normalize(app()->getLocale());
    }

    public function approve(ApproveIssueAction $approveIssue): void
    {
        $this->authorize('approve', $this->issue);
        $approveIssue->handle($this->issue, auth()->user());

        $this->refreshIssue();
    }

    public function openCloseModal(): void
    {
        $this->authorize('update', $this->issue);
        $this->closeReason = '';
        $this->resetValidation();
        $this->showCloseModal = true;
    }

    public function closeCloseModal(): void
    {
        $this->showCloseModal = false;
        $this->closeReason = '';
        $this->resetValidation();
    }

    public function openReopenModal(): void
    {
        $this->authorize('update', $this->issue);
        $this->reopenReason = '';
        $this->resetValidation();
        $this->showReopenModal = true;
    }

    public function closeReopenModal(): void
    {
        $this->showReopenModal = false;
        $this->reopenReason = '';
        $this->resetValidation();
    }

    public function reopenIssue(ReopenIssueAction $reopenIssue): void
    {
        $this->authorize('update', $this->issue);

        $this->reopenReason = trim($this->reopenReason);

        $validated = $this->validate([
            'reopenReason' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
        ], [
            'reopenReason.max' => __('issues.errors.text_max'),
        ]);

        $reopenIssue->handle($this->issue, auth()->user(), $validated['reopenReason'] ?: null);

        $this->closeReopenModal();
        $this->refreshIssue();
    }

    public function closeIssue(CloseIssueAction $closeIssue): void
    {
        $this->authorize('update', $this->issue);

        $this->closeReason = trim($this->closeReason);

        $validated = $this->validate([
            'closeReason' => ['required', 'string', 'min:2', 'max:'.TextDescriptionLimits::MAX],
        ], [
            'closeReason.required' => __('issues.errors.close_reason_required'),
            'closeReason.min' => __('issues.errors.close_reason_min'),
            'closeReason.max' => __('issues.errors.text_max'),
        ]);

        $closeIssue->handle($this->issue, auth()->user(), $validated['closeReason']);

        $this->closeCloseModal();
        $this->refreshIssue();
    }

    public function toggleRecurrencePause(ToggleIssueRecurrencePauseAction $toggle): void
    {
        $this->authorize('update', $this->issue);
        $toggle->handle($this->issue, (int) auth()->id());
        $this->refreshIssue();
    }

    public function openAddTaskModal(): void
    {
        $this->authorize('update', $this->issue);

        if (! $this->issue->isApproved()) {
            return;
        }

        $this->newTeamId = null;
        $this->taskNote = trim((string) $this->issue->description);
        $this->taskScheduledFor = $this->issue->recurrence_next_due_at?->format('Y-m-d');
        $this->taskPriority = 'prio_3';

        $this->resetValidation();
        $this->showAddTaskModal = true;
    }

    public function closeAddTaskModal(): void
    {
        $this->showAddTaskModal = false;
    }

    public function openEditTaskModal(int $taskId): void
    {
        $this->authorize('update', $this->issue);

        if (! $this->issue->isApproved()) {
            return;
        }

        $task = $this->issue->tasks->find($taskId);
        if (! $task) {
            return;
        }

        $this->editTaskId = $taskId;
        $this->newTeamId = $task->internal_team_id;
        $this->taskNote = trim((string) ($task->description ?: $this->issue->description));
        $this->taskPriority = $task->priority->value;
        $this->taskScheduledFor = $task->scheduled_for?->format('Y-m-d');

        $this->resetValidation();
        $this->showEditTaskModal = true;
    }

    public function closeEditTaskModal(): void
    {
        $this->showEditTaskModal = false;
        $this->editTaskId = null;
    }

    public function editTask(
        UpdateTaskPriorityAction $updatePriority,
        UpdateTaskTeamAction $updateTeam,
        UpdateTaskDetailsAction $updateDetails,
    ): void {
        $this->authorize('update', $this->issue);

        if (! $this->issue->isApproved()) {
            return;
        }

        $task = $this->issue->tasks->find($this->editTaskId);
        if (! $task) {
            return;
        }

        $this->taskNote = trim($this->taskNote);

        $validated = $this->validate([
            'newTeamId' => ['required', 'integer', 'exists:internal_teams,id'],
            'taskNote' => ['required', 'string', 'min:2', 'max:'.TextDescriptionLimits::MAX],
            'taskScheduledFor' => ['nullable', 'date'],
            'taskPriority' => ['required', 'string', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
        ], [
            'newTeamId.required' => __('issues.show.errors.team_required'),
            'taskNote.required' => __('issues.show.errors.task_note_required'),
            'taskNote.min' => __('issues.show.errors.task_note_min'),
            'taskNote.max' => __('issues.errors.text_max'),
        ]);

        // Update priority
        $updatePriority->handle(
            $task,
            TaskPriority::from($validated['taskPriority']),
            $this->issue->tenant_id,
            (int) auth()->id(),
        );

        // Update team if changed
        if ($task->internal_team_id !== (int) $validated['newTeamId']) {
            $updateTeam->handle($task, (int) $validated['newTeamId']);
        }

        $updateDetails->handle(
            $task,
            $validated['taskNote'],
            $validated['taskScheduledFor'] ?? null,
            $this->issue->tenant_id,
            (int) auth()->id(),
        );

        $this->closeEditTaskModal();
        $this->reset(['newTeamId', 'taskNote', 'taskScheduledFor', 'taskPriority']);

        $this->refreshIssue();
    }

    public function addTask(CreateTaskAction $createTask): void
    {
        $this->authorize('update', $this->issue);

        if (! $this->issue->isApproved()) {
            return;
        }

        $this->taskNote = trim($this->taskNote);

        $validated = $this->validate([
            'newTeamId' => ['required', 'integer', 'exists:internal_teams,id'],
            'taskNote' => ['required', 'string', 'min:2', 'max:'.TextDescriptionLimits::MAX],
            'taskScheduledFor' => ['nullable', 'date'],
            'taskPriority' => ['required', 'string', 'in:'.implode(',', array_column(TaskPriority::cases(), 'value'))],
        ], [
            'newTeamId.required' => __('issues.show.errors.team_required'),
            'taskNote.required' => __('issues.show.errors.task_note_required'),
            'taskNote.min' => __('issues.show.errors.task_note_min'),
            'taskNote.max' => __('issues.errors.text_max'),
        ]);

        $extra = [];
        if (! empty($validated['taskScheduledFor'])) {
            $extra['scheduled_for'] = $validated['taskScheduledFor'];
        }

        $createTask->handle(
            $this->issue,
            (int) $validated['newTeamId'],
            priority: TaskPriority::from($validated['taskPriority']),
            description: $validated['taskNote'],
            extra: $extra,
        );

        $this->closeAddTaskModal();
        $this->reset(['newTeamId', 'taskNote', 'taskScheduledFor', 'taskPriority']);

        $this->refreshIssue();
    }

    public function openUpdateModal(): void
    {
        $this->authorize('update', $this->issue);

        if (! $this->issue->isApproved()) {
            return;
        }

        $this->reset(['updateDescription', 'updatePhotos']);
        $this->resetValidation();
        $this->showUpdateModal = true;
        $this->dispatch('wp-prepare-photo-inputs');
    }

    public function closeUpdateModal(): void
    {
        $this->showUpdateModal = false;
        $this->reset(['updateDescription', 'updatePhotos']);
        $this->resetValidation();
        $this->dispatch('wp-clear-photo-previews');
    }

    public function saveUpdate(CreateIssueUpdateAction $createUpdate): void
    {
        $this->authorize('update', $this->issue);

        if (! $this->issue->isApproved()) {
            return;
        }

        $validated = $this->validate([
            'updateDescription' => ['required', 'string', 'min:2', 'max:'.TextDescriptionLimits::MAX],
            'updatePhotos' => ['nullable', 'array', 'max:4'],
            'updatePhotos.*' => ['image', 'max:10240'],
        ], [
            'updateDescription.required' => __('issues.updates.errors.description_required'),
            'updateDescription.min' => __('issues.updates.errors.description_min'),
            'updateDescription.max' => __('issues.errors.text_max'),
            'updatePhotos.max' => __('issues.updates.errors.photos_max'),
            'updatePhotos.*.image' => __('issues.updates.errors.photos_image'),
        ]);

        $createUpdate->handle(
            $this->issue,
            auth()->user(),
            $validated['updateDescription'],
            $this->updatePhotos,
        );

        $this->closeUpdateModal();

        $this->refreshIssue();
    }

    public function removeUpdatePhoto(int $index): void
    {
        if (isset($this->updatePhotos[$index])) {
            array_splice($this->updatePhotos, $index, 1);
        }
    }

    protected function refreshIssue(): void
    {
        $this->issue = $this->issue->fresh([
            'tasks.team',
            'tasks.translations',
            'translations',
            'photos' => fn ($q) => $q->orderBy('created_at'),
            'location',
            'unit.translations',
            'updates' => fn ($q) => $q->with(['user', 'worker', 'photos'])->latest(),
        ]);
    }

    public function render()
    {
        $issue = $this->issue->load([
            'tasks.team',
            'tasks.translations',
            'translations',
            'photos' => fn ($q) => $q->orderBy('created_at'),
            'location',
            'unit.translations',
            'updates' => fn ($q) => $q->with(['user', 'worker', 'photos'])->latest(),
        ]);

        $location = $issue->location;
        $headline = collect([$location?->localizedName(), $issue->unit?->localizedName()])->filter()->join(' · ');
        $addressLine = $location
            ? trim(($location->country_code ?: 'BE').' '.$location->formattedAddress())
            : '';

        $displayLocale = LocaleSupport::normalize($this->descriptionLocale);
        $descriptionText = $issue->isApproved()
            ? $issue->descriptionForDisplayLocale($displayLocale)
            : (string) $issue->description;
        $descriptionMissing = $issue->isApproved()
            && $issue->descriptionMissingForDisplayLocale($displayLocale);

        return view('livewire.issues.show', [
            'issue' => $issue,
            'teams' => InternalTeam::query()->orderBy('name')->get(),
            'priorities' => TaskPriority::cases(),
            'headline' => $headline,
            'addressLine' => $addressLine,
            'nav' => EntityDetailNavigation::forIssue($issue),
            'descriptionText' => $descriptionText,
            'descriptionMissing' => $descriptionMissing,
            'descriptionLocales' => config('locales.labels', []),
        ]);
    }
}
