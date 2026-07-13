<?php

namespace App\Livewire\Time;

use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Enums\TimePresenceAttentionType;
use App\Enums\TimePresenceStatusFilter;
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

    public function mount(): void
    {
        $this->authorize('viewAny', WorkShift::class);
    }

    public function setAttentionType(string $type = ''): void
    {
        $this->attentionType = $type === '' ? null : $type;
    }

    public function render(BuildTimePresenceDashboardAction $buildDashboard)
    {
        $tenantId = (int) Tenancy::id();
        $dashboard = $buildDashboard->handle(
            $tenantId,
            $this->teamFilter,
            null,
            $this->locationFilter,
            null,
            TimePresenceStatusFilter::Attention,
        );

        $typeFilter = TimePresenceAttentionType::tryFrom((string) $this->attentionType);
        $items = $dashboard->attentionItems;
        if ($typeFilter !== null) {
            $items = $items->filter(fn ($item) => $item->type === $typeFilter)->values();
        }

        $typeCounts = $dashboard->attentionItems
            ->groupBy(fn ($item) => $item->type->value)
            ->map->count();

        return view('livewire.time.alarms-index', [
            'items' => $items,
            'totalCount' => $dashboard->attentionItems->count(),
            'typeCounts' => $typeCounts,
            'attentionType' => $typeFilter,
            'teams' => InternalTeam::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'staleHours' => (int) config('time.stale_shift_hours', 16),
        ]);
    }
}
