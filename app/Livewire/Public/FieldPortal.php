<?php

namespace App\Livewire\Public;

use App\Actions\Issues\AddIssueUpdateAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Task;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.public')]
#[Title('WinProx')]
class FieldPortal extends Component
{
    public int $teamId;
    public int $tenantId;
    public string $teamName = '';

    public ?int $workerId = null;

    /** @var array<int, string> */
    public array $notes = [];

    public function mount(string $token): void
    {
        $team = InternalTeam::withoutGlobalScope('tenant')
            ->where('field_qr_token', $token)
            ->first();

        abort_unless($team, 404);

        $this->teamId = $team->id;
        $this->tenantId = $team->tenant_id;
        $this->teamName = $team->name;
    }

    public function booted(): void
    {
        Tenancy::actAs($this->tenantId);
    }

    public function selectWorker(int $worker): void
    {
        $exists = Worker::where('internal_team_id', $this->teamId)
            ->whereKey($worker)
            ->exists();

        abort_unless($exists, 404);

        $this->workerId = $worker;
    }

    public function signOut(): void
    {
        $this->workerId = null;
    }

    public function setStatus(int $task, string $status, UpdateTaskStatusAction $updateStatus): void
    {
        $target = TaskStatus::from($status);

        abort_unless(in_array($target, [TaskStatus::InProgress, TaskStatus::Done], true), 403);

        $model = Task::where('internal_team_id', $this->teamId)->findOrFail($task);
        $updateStatus->handle($model, $target);
    }

    public function addNote(int $task, AddIssueUpdateAction $addUpdate): void
    {
        $this->validate([
            "notes.$task" => ['required', 'string', 'max:1000'],
        ], [
            "notes.$task.required" => __('field-portal.errors.note_required'),
            "notes.$task.max" => __('field-portal.errors.note_max'),
        ]);

        $model = Task::where('internal_team_id', $this->teamId)->findOrFail($task);
        $addUpdate->handle($model->issue, $this->notes[$task], $this->workerId);

        unset($this->notes[$task]);
    }

    public function render()
    {
        $workers = Worker::where('internal_team_id', $this->teamId)
            ->orderBy('name')
            ->get();

        $tasks = $this->workerId
            ? Task::with(['issue', 'issue.location', 'issue.unit'])
                ->where('internal_team_id', $this->teamId)
                ->where('status', '!=', TaskStatus::Closed->value)
                ->orderByDesc('created_at')
                ->get()
            : collect();

        return view('livewire.public.field-portal', [
            'workers' => $workers,
            'tasks' => $tasks,
        ]);
    }
}
