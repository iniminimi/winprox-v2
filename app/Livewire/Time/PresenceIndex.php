<?php

namespace App\Livewire\Time;

use App\Actions\Time\BuildTimePresenceSnapshotAction;
use App\Actions\Time\ForceCloseWorkShiftAction;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
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

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'clock_point')]
    public ?int $clockPointFilter = null;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('viewAny', WorkShift::class);
    }

    public function forceClose(int $shiftId, ForceCloseWorkShiftAction $forceClose): void
    {
        $shift = WorkShift::query()->findOrFail($shiftId);
        $this->authorize('forceClose', $shift);

        $forceClose->handle($shift, (int) Tenancy::id(), auth()->id());
        session()->flash('time_flash', __('time.presence.force_closed'));
    }

    public function render(BuildTimePresenceSnapshotAction $buildPresence)
    {
        $tenantId = (int) Tenancy::id();
        $snapshot = $buildPresence->handle(
            $tenantId,
            $this->teamFilter,
            $this->clockPointFilter,
            $this->search,
        );

        return view('livewire.time.presence-index', [
            'snapshot' => $snapshot,
            'teams' => InternalTeam::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'clockPoints' => ClockPoint::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
