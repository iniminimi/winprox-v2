<?php

namespace App\Livewire;

use App\Actions\Billing\RealignSubscriptionPeriodAction;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Unit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Dashboard extends Component
{
    public function render(RealignSubscriptionPeriodAction $realign)
    {
        $stats = [
            'locations' => Location::query()->count(),
            'units' => Unit::query()->count(),
            'new_issues' => Issue::query()->where('status', TaskStatus::New->value)->count(),
            'open_tasks' => Task::query()->where('status', TaskStatus::InProgress->value)->count(),
        ];

        $recent = Issue::query()
            ->with(['location', 'unit', 'tasks.team'])
            ->latest()
            ->take(5)
            ->get();

        $tenant = auth()->user()?->tenant;
        if ($tenant !== null) {
            $tenant = $realign->handle($tenant);
        }

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'portalBatteryState' => $tenant?->portalDashboardBatteryState(),
            'hasNoLocationsOrUnits' => $stats['locations'] === 0 && $stats['units'] === 0,
        ]);
    }
}
