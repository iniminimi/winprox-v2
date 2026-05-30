<?php

namespace App\Livewire\Tasks;

use App\Actions\Tasks\PauseTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\Tasks\TaskStatusTransitions;
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

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->task = $task->load(['issue.location', 'issue.unit', 'issue.updates.user', 'issue.updates.worker', 'team']);
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
            $rules['reason'] = ['required', 'string', 'max:2000'];
        }

        $validated = $this->validate($rules, [
            'reason.required' => __('tasks.errors.reason_required'),
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
            'pauseNote' => ['required', 'string', 'max:2000'],
        ]);

        $pause->handle($this->task, $this->pauseNote, auth()->user());
        $this->pauseNote = '';
        $this->refreshTask();
    }

    protected function refreshTask(): void
    {
        $this->task = $this->task->fresh(['issue.location', 'issue.unit', 'issue.updates.user', 'issue.updates.worker', 'team']);
    }

    public function render()
    {
        $current = $this->task->status instanceof TaskStatus
            ? $this->task->status
            : TaskStatus::from((string) $this->task->status);

        $target = TaskStatus::tryFrom($this->targetStatus);

        return view('livewire.tasks.show', [
            'task' => $this->task,
            'transitions' => TaskStatusTransitions::nextOptions($current),
            'requiresReason' => $target !== null && TaskStatusTransitions::requiresReason($current, $target),
        ]);
    }
}
