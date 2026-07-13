<?php

namespace App\Livewire\Time;

use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Enums\TimePresenceStatusFilter;
use App\Enums\TimePresenceViewMode;
use App\Livewire\Concerns\ManagesWorkShiftForceClose;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class PresenceIndex extends Component
{
    use AuthorizesRequests;
    use ManagesWorkShiftForceClose;

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'clock_point')]
    public ?int $clockPointFilter = null;

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    #[Url(as: 'status')]
    public string $statusFilter = 'all';

    #[Url(as: 'view')]
    public string $viewMode = 'teams';

    #[Url]
    public string $search = '';

    /** @var list<int> */
    public array $expandedTeams = [];

    /** @var array<int, int> */
    public array $teamShiftLimits = [];

    public function mount(): void
    {
        $this->authorize('viewAny', WorkShift::class);

        if ($this->teamFilter !== null) {
            $this->expandedTeams = [$this->teamFilter];
        }
    }

    public function updatedTeamFilter(?int $value): void
    {
        $this->expandedTeams = $value !== null ? [$value] : [];
        $this->teamShiftLimits = [];
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = $mode;
    }

    public function toggleTeam(int $teamId): void
    {
        if (in_array($teamId, $this->expandedTeams, true)) {
            $this->expandedTeams = array_values(array_diff($this->expandedTeams, [$teamId]));
            unset($this->teamShiftLimits[$teamId]);
        } else {
            $this->expandedTeams[] = $teamId;
        }
    }

    public function openTeamCard(int $teamId): void
    {
        $this->viewMode = TimePresenceViewMode::Teams->value;
        $this->teamFilter = $teamId;
        $this->expandedTeams = [$teamId];
        $this->teamShiftLimits = [];
    }

    public function openLocationCard(?int $locationId): void
    {
        $this->viewMode = TimePresenceViewMode::Teams->value;
        $this->locationFilter = $locationId;
        $this->teamFilter = null;
        $this->expandedTeams = [];
        $this->teamShiftLimits = [];
    }

    public function loadMoreTeamShifts(int $teamId): void
    {
        $pageSize = (int) config('time.presence_team_page_size', 50);
        $current = $this->teamShiftLimits[$teamId] ?? $pageSize;
        $this->teamShiftLimits[$teamId] = $current + $pageSize;
    }

    public function render(BuildTimePresenceDashboardAction $buildDashboard)
    {
        $tenantId = (int) Tenancy::id();
        $dashboard = $buildDashboard->handle(
            $tenantId,
            $this->teamFilter,
            $this->clockPointFilter,
            $this->locationFilter,
            $this->search,
            TimePresenceStatusFilter::tryFromRequest($this->statusFilter),
            $this->expandedTeams,
        );

        return view('livewire.time.presence-index', [
            'dashboard' => $dashboard,
            'statusFilter' => TimePresenceStatusFilter::tryFromRequest($this->statusFilter),
            'viewMode' => TimePresenceViewMode::tryFromRequest($this->viewMode),
            'teams' => InternalTeam::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'clockPoints' => ClockPoint::query()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'staleHours' => (int) config('time.stale_shift_hours', 16),
            'teamPageSize' => (int) config('time.presence_team_page_size', 50),
        ]);
    }
}
