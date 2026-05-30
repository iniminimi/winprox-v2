<?php

namespace App\Livewire\Tasks;

use App\Actions\Tasks\CreateTaskAction;
use App\Actions\Tasks\UpdateTaskStatusAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Task;
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

    #[Url(as: 'q')]
    public string $search = '';

    public bool $recurring = false;

    public function applyFilters(): void
    {
        // Trigger re-render.
    }

    public function resetFilters(): void
    {
        $this->reset(['statusFilter', 'teamFilter', 'search', 'recurring']);
    }

    public function render()
    {
        $this->authorize('viewAny', Task::class);

        $tasks = Task::query()
            ->with(['issue.location', 'issue.unit', 'team'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->teamFilter, fn ($q) => $q->where('internal_team_id', $this->teamFilter))
            ->when($this->recurring, fn ($q) => $q->where('is_recurring_cycle', true))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($query) use ($term) {
                    $query->where('note', 'like', $term)
                        ->orWhereHas('issue', fn ($issue) => $issue
                            ->where('description', 'like', $term)
                            ->orWhere('reporter_name', 'like', $term)
                            ->orWhereHas('location', fn ($loc) => $loc
                                ->where('name', 'like', $term)
                                ->orWhere('address', 'like', $term))
                            ->orWhereHas('unit', fn ($unit) => $unit->where('name', 'like', $term)));
                });
            })
            ->latest()
            ->get();

        $newTasks = Task::query()
            ->with(['issue.location', 'issue.unit', 'team'])
            ->where('status', TaskStatus::New)
            ->latest()
            ->limit(5)
            ->get();

        $groups = [];
        foreach (TaskStatus::cases() as $status) {
            $bucket = $tasks->where('status', $status)->sortByDesc('created_at');
            if ($bucket->isNotEmpty()) {
                $groups[] = ['status' => $status, 'tasks' => $bucket->values()];
            }
        }

        return view('livewire.tasks.index', [
            'groups' => $groups,
            'newTasks' => $newTasks,
            'statuses' => TaskStatus::cases(),
            'teams' => InternalTeam::query()->orderBy('name')->get(),
            'hasFilters' => $this->statusFilter !== '' || $this->teamFilter || $this->search !== '' || $this->recurring,
        ]);
    }
}
