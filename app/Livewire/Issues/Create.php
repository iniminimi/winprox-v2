<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\AssignIssueTeamTaskAction;
use App\Actions\Issues\CreateManagerIssueAction;
use App\Http\Requests\Issues\AssignIssueTeamTaskRequest;
use App\Http\Requests\Issues\StoreManagerIssueStepOneRequest;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Unit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Create extends Component
{
    public int $step = 1;

    public ?int $issue_id = null;

    public ?int $location_id = null;

    public ?int $unit_id = null;

    public string $description = '';

    public bool $is_recurring = false;

    public int $recurrence_interval_value = 1;

    public string $recurrence_interval_unit = 'month';

    public int $recurrence_lead_days = 30;

    public ?string $recurrence_first_due_date = null;

    public ?int $internal_team_id = null;

    public ?string $task_note = null;

    public function updatedLocationId(): void
    {
        $this->unit_id = null;
    }

    public function saveStepOne(CreateManagerIssueAction $createIssue): void
    {
        $this->authorize('create', Issue::class);

        if (blank($this->unit_id)) {
            $this->unit_id = null;
        }
        if (blank($this->location_id)) {
            $this->location_id = null;
        }

        $validated = $this->validate(
            StoreManagerIssueStepOneRequest::ruleSet(),
            (new StoreManagerIssueStepOneRequest)->messages(),
        );

        $issue = $createIssue->handle($validated, auth()->user());

        $this->issue_id = $issue->id;
        $this->step = 2;
    }

    public function saveStepTwo(AssignIssueTeamTaskAction $assignTask): mixed
    {
        $issue = Issue::query()->findOrFail($this->issue_id);
        $this->authorize('create', Issue::class);

        $validated = $this->validate(AssignIssueTeamTaskRequest::ruleSet());

        $assignTask->handle(
            $issue,
            (int) $validated['internal_team_id'],
            $validated['task_note'] ?? null,
        );

        session()->flash('highlight_issue', $issue->id);

        return $this->redirectRoute('issues.index', ['highlight' => $issue->id], navigate: false);
    }

    public function backToStepOne(): void
    {
        $this->step = 1;
    }

    public function render()
    {
        return view('livewire.issues.create', [
            'locations' => Location::query()->orderBy('name')->get(),
            'units' => $this->location_id
                ? Unit::query()->where('location_id', $this->location_id)->orderBy('name')->get()
                : collect(),
            'teams' => InternalTeam::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
