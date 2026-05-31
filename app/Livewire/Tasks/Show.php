<?php

namespace App\Livewire\Tasks;

use App\Actions\Tasks\PauseTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Actions\Tasks\UpdateTaskTeamAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Task;
use App\Support\EntityDetailNavigation;
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

    public ?int $teamId = null;

    public function mount(Task $task): void
    {
        $this->authorize('view', $task);
        $this->task = $task->load(['issue.location', 'issue.unit', 'issue.updates.user', 'issue.updates.worker', 'team']);
        $this->teamId = $task->internal_team_id;
    }

    public function saveTeam(UpdateTaskTeamAction $updateTeam): void
    {
        $this->authorize('update', $this->task);

        $validated = $this->validate([
            'teamId' => ['required', 'integer', 'exists:internal_teams,id'],
        ], [
            'teamId.required' => __('tasks.show.errors.team_required'),
        ]);

        $this->task = $updateTeam->handle($this->task, (int) $validated['teamId']);
        $this->teamId = $this->task->internal_team_id;
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
        $this->teamId = $this->task->internal_team_id;
    }

    public function render()
    {
        $current = $this->task->status instanceof TaskStatus
            ? $this->task->status
            : TaskStatus::from((string) $this->task->status);

        $target = TaskStatus::tryFrom($this->targetStatus);

        $issue = $this->task->issue;
        $location = $issue?->location;
        $headline = collect([$location?->name, $issue?->unit?->name])->filter()->join(' · ');
        if ($headline === '' && $issue) {
            $headline = \Illuminate\Support\Str::limit($issue->description, 80);
        }
        $addressLine = $location
            ? trim(($location->country_code ?: 'BE').' '.$location->formattedAddress())
            : '';

        return view('livewire.tasks.show', [
            'task' => $this->task,
            'headline' => $headline,
            'addressLine' => $addressLine,
            'teams' => InternalTeam::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'transitions' => TaskStatusTransitions::nextOptions($current),
            'requiresReason' => $target !== null && TaskStatusTransitions::requiresReason($current, $target),
            'nav' => EntityDetailNavigation::forTask($this->task),
        ]);
    }
}
