<?php

namespace App\Livewire\Time;

use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Actions\Time\EnsureTimeRosterQrTokenAction;
use App\Enums\TimePresenceStatusFilter;
use App\Enums\TimePresenceViewMode;
use App\Livewire\Concerns\ManagesWorkShiftForceClose;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrSvg;
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
    public string $viewMode = 'board';

    #[Url]
    public string $search = '';

    /** @var list<int> */
    public array $expandedTeams = [];

    /** @var array<int, int> */
    public array $teamShiftLimits = [];

    public int $boardLimit = 0;

    public function mount(): void
    {
        $this->authorize('viewAny', WorkShift::class);

        $tenant = Tenant::query()->find(Tenancy::id());
        if ($tenant !== null) {
            app(EnsureTimeRosterQrTokenAction::class)->handle($tenant, auth()->id());
        }

        if ($this->teamFilter !== null) {
            $this->expandedTeams = [$this->teamFilter];
        }
    }

    public function updatedTeamFilter(?int $value): void
    {
        $this->expandedTeams = $value !== null ? [$value] : [];
        $this->teamShiftLimits = [];
        $this->boardLimit = 0;
    }

    public function updatedLocationFilter(): void
    {
        $this->boardLimit = 0;
    }

    public function updatedClockPointFilter(): void
    {
        $this->boardLimit = 0;
    }

    public function updatedSearch(): void
    {
        $this->boardLimit = 0;
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->boardLimit = 0;
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = TimePresenceViewMode::tryFromRequest($mode)->value;
        $this->expandedTeams = [];
        $this->teamShiftLimits = [];
        $this->boardLimit = 0;
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

    public function loadMoreBoard(): void
    {
        $pageSize = (int) config('time.presence_team_page_size', 50);
        $this->boardLimit = ($this->boardLimit > 0 ? $this->boardLimit : $pageSize) + $pageSize;
    }

    public function render(BuildTimePresenceDashboardAction $buildDashboard)
    {
        $tenantId = (int) Tenancy::id();
        $viewMode = TimePresenceViewMode::tryFromRequest($this->viewMode);
        $expandedTeamIds = $this->expandedTeams;
        $includeAbsentRoster = false;

        if ($viewMode === TimePresenceViewMode::Board) {
            $expandedTeamIds = $this->teamFilter !== null
                ? [(int) $this->teamFilter]
                : InternalTeam::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            $includeAbsentRoster = true;
        }

        $user = auth()->user();
        $scopeLocationIds = $user?->accessibleLocationIds();

        $locationsQuery = Location::query()->orderBy('name');
        if ($scopeLocationIds !== null) {
            $locationsQuery->whereIn('id', $scopeLocationIds);
        }

        $dashboard = $buildDashboard->handle(
            $tenantId,
            $this->teamFilter,
            $this->clockPointFilter,
            $this->locationFilter,
            $this->search,
            TimePresenceStatusFilter::tryFromRequest($this->statusFilter),
            $expandedTeamIds,
            $includeAbsentRoster,
            $scopeLocationIds,
        );

        $tenant = Tenant::query()->find($tenantId);
        $rosterUrl = $tenant?->timeRosterPortalUrl();

        return view('livewire.time.presence-index', [
            'dashboard' => $dashboard,
            'teams' => InternalTeam::query()->where('is_active', true)->with('translations')->orderBy('sort_order')->orderBy('name')->get(),
            'clockPoints' => ClockPoint::query()->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => $locationsQuery->get(),
            'staleHours' => (int) config('time.stale_shift_hours', 16),
            'teamPageSize' => (int) config('time.presence_team_page_size', 50),
            'boardLimit' => $this->boardLimit,
            'rosterQrUrl' => $rosterUrl,
            'rosterQrSvg' => $rosterUrl !== null ? QrSvg::svg($rosterUrl, 180) : null,
            'rosterCenterLogoUrl' => QrCenterLogo::publicUrl($tenant),
        ]);
    }
}
