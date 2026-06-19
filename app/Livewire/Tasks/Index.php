<?php

namespace App\Livewire\Tasks;

use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
use App\Models\Task;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\PerStatusListLimit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'priority')]
    public string $priorityFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'recurring')]
    public bool $recurring = false;

    #[Url(as: 'limit')]
    public int $perStatusLimit = PerStatusListLimit::DEFAULT;

    public function mount(): void
    {
        $this->perStatusLimit = PerStatusListLimit::normalize($this->perStatusLimit);
    }

    public function updatedPerStatusLimit(): void
    {
        $this->perStatusLimit = PerStatusListLimit::normalize($this->perStatusLimit);
    }

    public function applyFilters(): void
    {
        $this->redirect(route('tasks.index', array_filter([
            'status' => $this->statusFilter !== '' ? $this->statusFilter : null,
            'team' => $this->teamFilter ?: null,
            'priority' => $this->priorityFilter !== '' ? $this->priorityFilter : null,
            'q' => trim($this->search) !== '' ? trim($this->search) : null,
            'recurring' => $this->recurring ? '1' : null,
            'limit' => $this->perStatusLimit !== PerStatusListLimit::DEFAULT ? $this->perStatusLimit : null,
        ])), navigate: true);
    }

    public function resetFilters(): void
    {
        $this->redirect(route('tasks.index'), navigate: true);
    }

    public function render()
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->forApprovedIssue()
            ->with(['issue.location', 'issue.unit.translations', 'issue.translations', 'translations', 'team'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->statusFilter === '', fn ($q) => $q->where('status', '!=', TaskStatus::Closed))
            ->when($this->priorityFilter !== '', fn ($q) => $q->where('priority', $this->priorityFilter))
            ->when($this->teamFilter, fn ($q) => $q->where('internal_team_id', $this->teamFilter))
            ->when($this->recurring, fn ($q) => $q->where('is_recurring_cycle', true))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($query) use ($term) {
                    $query->where('description', 'like', $term)
                        ->orWhereHas('issue', fn ($issue) => $issue
                            ->where('description', 'like', $term)
                            ->orWhere('reporter_name', 'like', $term)
                            ->orWhereHas('location', fn ($loc) => $loc->where(function ($locationQuery) use ($term) {
                                $locationQuery->where('name', 'like', $term)
                                    ->orWhere('street', 'like', $term)
                                    ->orWhere('house_number', 'like', $term)
                                    ->orWhere('postal_code', 'like', $term)
                                    ->orWhere('city', 'like', $term)
                                    ->orWhere('address', 'like', $term);
                            }))
                            ->orWhereHas('unit', fn ($unit) => $unit->where('name', 'like', $term)));
                });
            })
            ->orderByDesc('id')
            ->get();

        $groups = [];
        foreach (TaskStatus::cases() as $status) {
            $bucket = $tasks->where('status', $status)->values();
            if ($bucket->isNotEmpty()) {
                $bucket = $bucket->values();
                $limited = $bucket->take($this->perStatusLimit)->values();
                $groups[] = [
                    'status' => $status,
                    'tasks' => $limited,
                    'shown' => $limited->count(),
                    'total' => $bucket->count(),
                ];
            }
        }

        $hasNoIssues = Issue::query()->count() === 0;

        return view('livewire.tasks.index', [
            'groups' => $groups,
            'perStatusLimits' => PerStatusListLimit::OPTIONS,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'teams' => InternalTeam::query()->orderBy('name')->get(),
            'hasFilters' => $this->statusFilter !== '' || $this->teamFilter || $this->priorityFilter !== '' || $this->search !== '' || $this->recurring,
            'onboarding' => TenantOnboardingState::current(),
            'hasNoIssues' => $hasNoIssues,
        ]);
    }
}
