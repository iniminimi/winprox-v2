<?php

namespace App\Livewire\Issues;

use App\Actions\Issues\ApproveIssueAction;
use App\Enums\TaskStatus;
use App\Models\InternalTeam;
use App\Models\Issue;
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

    #[Url(as: 'highlight')]
    public ?int $highlightIssue = null;

    public bool $recurring = false;

    public function mount(): void
    {
        if ($this->highlightIssue === null && session()->has('highlight_issue')) {
            $this->highlightIssue = (int) session()->pull('highlight_issue');
        }
    }

    public function approve(int $issue, ApproveIssueAction $approveIssue): void
    {
        $model = Issue::findOrFail($issue);
        $this->authorize('approve', $model);

        $approveIssue->handle($model, auth()->user());
    }

    public function applyFilters(): void
    {
        // Pas de (uitgestelde) filters toe; query draait opnieuw bij render.
    }

    public function resetFilters(): void
    {
        $this->reset(['statusFilter', 'teamFilter', 'search', 'recurring']);
    }

    public function render()
    {
        $this->authorize('viewAny', Issue::class);

        $issues = Issue::query()
            ->with(['location', 'unit', 'tasks.team'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->teamFilter, fn ($q) => $q->whereHas('tasks', fn ($t) => $t->where('internal_team_id', $this->teamFilter)))
            ->when($this->recurring, fn ($q) => $q->where('is_recurring', true))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($query) use ($term) {
                    $query->where('description', 'like', $term)
                        ->orWhere('reporter_name', 'like', $term)
                        ->orWhereHas('location', fn ($loc) => $loc
                            ->where('name', 'like', $term)
                            ->orWhere('address', 'like', $term))
                        ->orWhereHas('unit', fn ($unit) => $unit->where('name', 'like', $term));
                });
            })
            ->latest()
            ->get();

        $groups = [];
        foreach (TaskStatus::cases() as $status) {
            $bucket = $issues->where('status', $status)->sortByDesc('created_at');
            if ($bucket->isNotEmpty()) {
                $groups[] = ['status' => $status, 'issues' => $bucket->values()];
            }
        }

        return view('livewire.issues.index', [
            'groups' => $groups,
            'total' => $issues->count(),
            'statuses' => TaskStatus::cases(),
            'teams' => InternalTeam::query()->orderBy('name')->get(),
            'hasFilters' => $this->statusFilter !== '' || $this->teamFilter || $this->search !== '' || $this->recurring,
            'highlightIssue' => $this->highlightIssue,
        ]);
    }
}
