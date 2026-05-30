<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Show extends Component
{
    public Issue $issue;

    public ?int $newTeamId = null;

    public function mount(Issue $issue): void
    {
        $this->issue = $issue;
    }

    public function approve(ApproveIssueAction $approveIssue): void
    {
        $approveIssue->handle($this->issue, auth()->user());

        $this->refreshIssue();
    }

    public function changeTaskStatus(int $task, string $status, UpdateTaskStatusAction $updateStatus): void
    {
        $model = $this->issue->tasks()->findOrFail($task);
        $this->authorize('update', $model);

        $updateStatus->handle($model, TaskStatus::from($status), auth()->user());

        $this->refreshIssue();
    }

    public function addTask(CreateTaskAction $createTask): void
    {
        $this->validate(['newTeamId' => ['required', 'integer', 'exists:internal_teams,id']]);

        $createTask->handle($this->issue, $this->newTeamId);

        $this->newTeamId = null;

        $this->refreshIssue();
    }

    protected function refreshIssue(): void
    {
        $this->issue = $this->issue->fresh(['tasks.team', 'photos']);
    }

    public function render()
    {
        return view('livewire.issues.show', [
            'issue' => $this->issue->load(['tasks.team', 'photos', 'location', 'unit']),
            'statuses' => TaskStatus::cases(),
            'teams' => InternalTeam::query()->orderBy('name')->get(),
        ]);
    }
}
