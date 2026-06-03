<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueUpdateAction;
use App\Actions\Issues\ToggleIssueRecurrencePauseAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Support\EntityDetailNavigation;
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

    public bool $showUpdateModal = false;

    public ?int $newTeamId = null;

    public string $taskNote = '';

    public ?string $taskScheduledFor = null;

    public string $updateBody = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $updatePhotos = [];

    public function mount(Issue $issue): void
    {
        $this->authorize('view', $issue);
        $this->issue = $issue;
    }

    public function approve(ApproveIssueAction $approveIssue): void
    {
        $this->authorize('approve', $this->issue);
        $approveIssue->handle($this->issue, auth()->user());

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

        $this->newTeamId = null;
        $this->taskNote = trim((string) $this->issue->description);
        $this->taskScheduledFor = $this->issue->recurrence_next_due_at?->format('Y-m-d');

        $this->resetValidation();
        $this->showAddTaskModal = true;
    }

    public function closeAddTaskModal(): void
    {
        $this->showAddTaskModal = false;
    }

    public function addTask(CreateTaskAction $createTask): void
    {
        $this->authorize('update', $this->issue);

        $this->taskNote = trim($this->taskNote);

        $validated = $this->validate([
            'newTeamId' => ['required', 'integer', 'exists:internal_teams,id'],
            'taskNote' => ['required', 'string', 'min:2', 'max:5000'],
            'taskScheduledFor' => ['nullable', 'date'],
        ], [
            'newTeamId.required' => __('issues.show.errors.team_required'),
            'taskNote.required' => __('issues.show.errors.task_note_required'),
            'taskNote.min' => __('issues.show.errors.task_note_min'),
        ]);

        $extra = [];
        if (! empty($validated['taskScheduledFor'])) {
            $extra['scheduled_for'] = $validated['taskScheduledFor'];
        }

        $createTask->handle(
            $this->issue,
            (int) $validated['newTeamId'],
            note: $validated['taskNote'],
            extra: $extra,
        );

        $this->closeAddTaskModal();
        $this->reset(['newTeamId', 'taskNote', 'taskScheduledFor']);

        $this->refreshIssue();
    }

    public function openUpdateModal(): void
    {
        $this->authorize('update', $this->issue);

        $this->reset(['updateBody', 'updatePhotos']);
        $this->resetValidation();
        $this->showUpdateModal = true;
        $this->dispatch('wp-prepare-photo-inputs');
    }

    public function closeUpdateModal(): void
    {
        $this->showUpdateModal = false;
        $this->reset(['updateBody', 'updatePhotos']);
        $this->resetValidation();
        $this->dispatch('wp-clear-photo-previews');
    }

    public function saveUpdate(CreateIssueUpdateAction $createUpdate): void
    {
        $this->authorize('update', $this->issue);

        $validated = $this->validate([
            'updateBody' => ['required', 'string', 'min:2', 'max:5000'],
            'updatePhotos' => ['nullable', 'array', 'max:4'],
            'updatePhotos.*' => ['image', 'max:10240'],
        ], [
            'updateBody.required' => __('issues.updates.errors.body_required'),
            'updateBody.min' => __('issues.updates.errors.body_min'),
            'updatePhotos.max' => __('issues.updates.errors.photos_max'),
            'updatePhotos.*.image' => __('issues.updates.errors.photos_image'),
        ]);

        $createUpdate->handle(
            $this->issue,
            auth()->user(),
            $validated['updateBody'],
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
            'photos' => fn ($q) => $q->orderBy('created_at'),
            'location',
            'unit',
            'updates' => fn ($q) => $q->with(['user', 'worker', 'photos'])->latest(),
        ]);
    }

    public function render()
    {
        $issue = $this->issue->load([
            'tasks.team',
            'photos' => fn ($q) => $q->orderBy('created_at'),
            'location',
            'unit',
            'updates' => fn ($q) => $q->with(['user', 'worker', 'photos'])->latest(),
        ]);

        $location = $issue->location;
        $headline = collect([$location?->name, $issue->unit?->name])->filter()->join(' · ');
        $addressLine = $location
            ? trim(($location->country_code ?: 'BE').' '.$location->formattedAddress())
            : '';

        return view('livewire.issues.show', [
            'issue' => $issue,
            'teams' => InternalTeam::query()->orderBy('name')->get(),
            'headline' => $headline,
            'addressLine' => $addressLine,
            'nav' => EntityDetailNavigation::forIssue($issue),
        ]);
    }
}
