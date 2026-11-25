<?php

namespace App\Livewire\Time;

use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Enums\TimePresenceAttentionType;
use App\Enums\TimePresenceStatusFilter;
use App\Livewire\Concerns\ManagesWorkShiftForceClose;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class AlarmsIndex extends Component
{
    use AuthorizesRequests;
    use ManagesWorkShiftForceClose;

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    #[Url(as: 'type')]
    public ?string $attentionType = null;

    public int $shownLimit = 0;

    public function mount(): void
    {
        $this->authorize('viewAny', WorkShift::class);
        $this->shownLimit = $this->alarmsPageSize();
    }

    public function updatedTeamFilter(): void
    {
        $this->resetShownLimit();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetShownLimit();
    }

    public function setAttentionType(string $type = ''): void
    {
        $this->attentionType = $type === '' ? null : $type;
        $this->resetShownLimit();
    }

    public function loadMore(): void
    {
        $this->shownLimit += $this->alarmsPageSize();
    }

    private function resetShownLimit(): void
    {
        $this->shownLimit = $this->alarmsPageSize();
    }

    private function alarmsPageSize(): int
    {
        return max(1, (int) config('time.presence_team_page_size', 50));
    }

    public function render(BuildTimePresenceDashboardAction $buildDashboard)
    {
        $tenantId = (int) Tenancy::id();
        $viewer = auth()->user();
        $allowed = $viewer instanceof User ? $viewer->accessibleLocationIds() : null;
        if ($allowed !== null && $this->locationFilter !== null && ! in_array((int) $this->locationFilter, $allowed, true)) {
            $this->locationFilter = null;
        }
        $dashboard = $buildDashboard->handle(
            tenantId: $tenantId,
            teamId: $this->teamFilter,
            locationId: $this->locationFilter,
            statusFilter: TimePresenceStatusFilter::Attention,
            scopeLocationIds: $allowed,
            includeHistoricalRosterViews: true,
            attentionOnly: true,
        );

        $typeFilter = TimePresenceAttentionType::tryFrom((string) $this->attentionType);
        $items = $dashboard->attentionItems;
        if ($typeFilter !== null) {
            $items = $items->filter(fn ($item) => $item->type === $typeFilter)->values();
        }

        $filteredCount = $items->count();
        $visibleItems = $items->take(max(1, $this->shownLimit));
        $pageSize = $this->alarmsPageSize();

        $typeCounts = $dashboard->attentionItems
            ->groupBy(fn ($item) => $item->type->value)
            ->map->count();

        return view('livewire.time.alarms-index', [
            'items' => $visibleItems,
            'filteredCount' => $filteredCount,
            'totalCount' => $dashboard->attentionItems->count(),
            'alarmCount' => null,
            'typeCounts' => $typeCounts,
            'hasMore' => $filteredCount > $visibleItems->count(),
            'pageSize' => $pageSize,
            'teams' => InternalTeam::query()->where('is_active', true)->with('translations')->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::query()
                ->when($viewer instanceof User, fn ($query) => $query->accessibleTo($viewer))
                ->orderBy('name')
                ->get(),
            'staleHours' => (int) config('time.stale_shift_hours', 16),
        ]);
    }
}
