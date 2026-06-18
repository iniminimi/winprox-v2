<?php

namespace App\Livewire;

use App\Actions\Billing\RealignSubscriptionPeriodAction;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Unit;
use App\Support\Onboarding\TenantOnboardingState;
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
            'open_tasks' => Task::query()->forApprovedIssue()->whereIn('status', TaskStatus::openValues())->count(),
        ];

        $recent = Issue::query()
            ->where('status', '!=', TaskStatus::Closed->value)
            ->with(['location', 'unit', 'tasks.team'])
            ->get()
            ->sortBy(fn ($issue) => [
                $issue->status->sortOrder(),
                $issue->tasks->min(fn ($t) => $t->priority?->sortOrder() ?? 99),
                $issue->created_at->timestamp,
            ])
            ->take(5)
            ->values();

        $tenant = auth()->user()?->tenant;
        if ($tenant !== null) {
            $tenant = $realign->handle($tenant);
        }

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'portalBatteryState' => $tenant?->portalDashboardBatteryState(),
            'onboarding' => TenantOnboardingState::current(),
        ]);
    }
}
