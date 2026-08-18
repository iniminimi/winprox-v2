<?php

namespace App\Livewire;

use App\Actions\Billing\ApplyPlanEntitlementsAction;
use App\Actions\Billing\RealignSubscriptionPeriodAction;
use App\Actions\Dashboard\BuildDashboardStatsAction;
use App\Actions\Dashboard\ListDashboardRecentIssuesAction;
use App\Actions\Onboarding\ApplyTenantStarterPackAction;
use App\Actions\Onboarding\RemoveTenantStarterPackAction;
use App\Data\Onboarding\ApplyTenantStarterPackData;
use App\Enums\TenantStarterPackType;
use App\Http\Requests\Onboarding\ApplyTenantStarterPackRequest;
use App\Models\Category;
use App\Models\InternalTeam;
use App\Models\User;
use App\Support\Admin\AdminHealthService;
use App\Support\Dashboard\TopScannedUnitsService;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Onboarding\TenantStarterPackCatalog;
use App\Support\Onboarding\TenantStarterPackSummary;
use App\Support\Tenancy;
use App\Support\Translation\LocaleSupport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Dashboard extends Component
{
    public bool $showStarterPackModal = false;

    public bool $showRemoveStarterPackModal = false;

    public string $starterPackType = '';

    public function openStarterPackModal(): void
    {
        $this->authorize('create', InternalTeam::class);
        $this->authorize('create', Category::class);

        $this->resetValidation();
        $this->starterPackType = '';
        $this->showStarterPackModal = true;
    }

    public function closeStarterPackModal(): void
    {
        $this->showStarterPackModal = false;
        $this->starterPackType = '';
        $this->resetValidation();
    }

    public function applyStarterPack(ApplyTenantStarterPackAction $apply): void
    {
        $this->authorize('create', InternalTeam::class);
        $this->authorize('create', Category::class);

        $validated = $this->validate(
            ApplyTenantStarterPackRequest::ruleSet(),
            ApplyTenantStarterPackRequest::messageSet(),
        );

        $user = auth()->user();
        abort_unless($user instanceof User && $user->tenant !== null, 403);

        $apply->handle(
            $user->tenant,
            ApplyTenantStarterPackData::fromValidated($validated, $user->locale),
            $user,
        );

        $user->unsetRelation('tenant');
        $this->closeStarterPackModal();
    }

    public function openRemoveStarterPackModal(): void
    {
        $this->authorize('create', InternalTeam::class);
        $this->authorize('create', Category::class);

        $this->resetValidation();
        $this->showRemoveStarterPackModal = true;
    }

    public function closeRemoveStarterPackModal(): void
    {
        $this->showRemoveStarterPackModal = false;
        $this->resetValidation();
    }

    public function removeStarterPack(RemoveTenantStarterPackAction $remove): void
    {
        $this->authorize('create', InternalTeam::class);
        $this->authorize('create', Category::class);

        $user = auth()->user();
        abort_unless($user instanceof User && $user->tenant !== null, 403);

        $remove->handle($user->tenant, $user);

        $user->unsetRelation('tenant');
        $this->closeRemoveStarterPackModal();
    }

    public function render(
        RealignSubscriptionPeriodAction $realign,
        ApplyPlanEntitlementsAction $applyEntitlements,
        BuildDashboardStatsAction $buildStats,
        ListDashboardRecentIssuesAction $listRecentIssues,
        AdminHealthService $healthService,
        TopScannedUnitsService $topScannedUnits,
    ) {
        $tenant = auth()->user()?->tenant;
        if ($tenant !== null) {
            $tenant = $realign->handle($tenant);
            if ($tenant->isTrialActive() && ! $tenant->hasTimeModule()) {
                $tenant = $applyEntitlements->handle($tenant);
            }
        }

        $tenantId = (int) (Tenancy::id() ?? $tenant?->id ?? 0);
        $hasTimeModule = $tenant?->hasTimeModule() ?? false;
        $hasIotModule = $tenant?->hasIotModule() ?? false;
        $onboarding = TenantOnboardingState::current();
        $canApplyStarterPack = $onboarding->canApplyStarterPack()
            && ! ($tenant?->hasStarterPack() ?? false)
            && (auth()->user()?->can('create', InternalTeam::class) ?? false)
            && (auth()->user()?->can('create', Category::class) ?? false);

        $starterPackType = TenantStarterPackType::tryFrom($this->starterPackType);
        $starterPackPreview = $starterPackType instanceof TenantStarterPackType
            ? TenantStarterPackCatalog::preview(
                $starterPackType,
                LocaleSupport::normalize(auth()->user()?->locale ?? app()->getLocale()),
            )
            : null;

        $stats = $buildStats->handle($tenantId, $hasTimeModule, $hasIotModule);
        $recent = $listRecentIssues->handle($tenantId);

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'portalBatteryState' => $tenant?->portalDashboardBatteryState(),
            'onboarding' => $onboarding,
            'health' => $healthService->report(),
            'topScannedUnits' => $topScannedUnits->topForCurrentTenant(),
            'hasTimeModule' => $hasTimeModule,
            'canApplyStarterPack' => $canApplyStarterPack,
            'canManageStarterPack' => (auth()->user()?->can('create', InternalTeam::class) ?? false)
                && (auth()->user()?->can('create', Category::class) ?? false),
            'starterPackSummary' => $tenant !== null ? TenantStarterPackSummary::for($tenant) : null,
            'starterPackTypes' => TenantStarterPackType::cases(),
            'starterPackPreview' => $starterPackPreview,
        ]);
    }
}
