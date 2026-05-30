<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueUpdateAction;
use App\Actions\Issues\ToggleIssueRecurrencePauseAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Models\InternalTeam;
use App\Models\Issue;
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

    public ?int $newTeamId = null;

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

    public function addTask(CreateTaskAction $createTask): void
    {
        $this->validate(['newTeamId' => ['required', 'integer', 'exists:internal_teams,id']]);

        $createTask->handle($this->issue, $this->newTeamId);

        $this->newTeamId = null;

        $this->refreshIssue();
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

        $this->reset(['updateBody', 'updatePhotos']);
        $this->dispatch('wp-clear-photo-previews');

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
            'photos',
            'location',
            'unit',
            'updates.user',
            'updates.worker',
            'updates.photos',
        ]);
    }

    public function render()
    {
        return view('livewire.issues.show', [
            'issue' => $this->issue->load([
                'tasks.team',
                'photos',
                'location',
                'unit',
                'updates' => fn ($q) => $q->with(['user', 'worker', 'photos'])->latest(),
            ]),
            'teams' => InternalTeam::query()->orderBy('name')->get(),
        ]);
    }
}
