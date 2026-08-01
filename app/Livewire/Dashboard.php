<?php

namespace App\Livewire;

use App\Actions\Billing\RealignSubscriptionPeriodAction;
use App\Actions\Dashboard\BuildDashboardStatsAction;
use App\Actions\Dashboard\ListDashboardRecentIssuesAction;
use App\Support\Admin\AdminHealthService;
use App\Support\Dashboard\TopScannedUnitsService;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Dashboard extends Component
{
    public function render(
        RealignSubscriptionPeriodAction $realign,
        BuildDashboardStatsAction $buildStats,
        ListDashboardRecentIssuesAction $listRecentIssues,
        AdminHealthService $healthService,
        TopScannedUnitsService $topScannedUnits,
    ) {
        $tenant = auth()->user()?->tenant;
        if ($tenant !== null) {
            $tenant = $realign->handle($tenant);
        }

        $tenantId = (int) (Tenancy::id() ?? $tenant?->id ?? 0);
        $hasTimeModule = $tenant?->hasTimeModule() ?? false;
        $hasIotModule = $tenant?->hasIotModule() ?? false;

        $stats = $buildStats->handle($tenantId, $hasTimeModule, $hasIotModule);
        $recent = $listRecentIssues->handle($tenantId);

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'portalBatteryState' => $tenant?->portalDashboardBatteryState(),
            'onboarding' => TenantOnboardingState::current(),
            'health' => $healthService->report(),
            'topScannedUnits' => $topScannedUnits->topForCurrentTenant(),
            'hasTimeModule' => $hasTimeModule,
        ]);
    }
}
