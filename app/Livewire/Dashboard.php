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
use App\Models\Tenant;
use App\Models\User;
use App\Support\Admin\AdminHealthService;
use App\Support\Dashboard\TopScannedUnitsService;
use App\Support\Onboarding\TenantOnboardingState;
use App\Support\Onboarding\TenantStarterPackCatalog;
use App\Support\Onboarding\TenantStarterPackSummary;
use App\Support\Platform\SupportTenantContext;
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
        $this->authorize('applyStarterPack', $this->starterPackTenant());

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
        $user = $this->starterPackActor();
        $tenant = $this->starterPackTenant();
        $this->authorize('applyStarterPack', $tenant);

        $validated = $this->validate(
            ApplyTenantStarterPackRequest::ruleSet(),
            ApplyTenantStarterPackRequest::messageSet(),
        );

        $apply->handle(
            $tenant,
            ApplyTenantStarterPackData::fromValidated($validated, $user->locale),
            $user,
        );

        $user->unsetRelation('tenant');
        $this->closeStarterPackModal();
    }

    public function openRemoveStarterPackModal(): void
    {
        $this->authorize('removeStarterPack', $this->starterPackTenant());

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
        $user = $this->starterPackActor();
        $tenant = $this->starterPackTenant();
        $this->authorize('removeStarterPack', $tenant);

        $remove->handle($tenant, $user);

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
        $tenant = $this->resolveTenant();
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
        $user = auth()->user();
        $canManageStarterPack = $user instanceof User
            && $tenant !== null
            && $user->can('removeStarterPack', $tenant);
        $canApplyStarterPack = $onboarding->canApplyStarterPack
            && ! ($tenant?->hasStarterPack() ?? false)
            && $user instanceof User
            && $tenant !== null
            && $user->can('applyStarterPack', $tenant);

        $starterPackType = TenantStarterPackType::tryFrom($this->starterPackType);
        $starterPackPreview = $starterPackType instanceof TenantStarterPackType
            ? TenantStarterPackCatalog::preview(
                $starterPackType,
                LocaleSupport::normalize($user?->locale ?? app()->getLocale()),
            )
            : null;

        $stats = $buildStats->handle($tenantId, $hasTimeModule, $hasIotModule);
        $recent = $listRecentIssues->handle($tenantId);
        $starterPackSummary = $tenant !== null ? TenantStarterPackSummary::for($tenant) : null;

        return view('livewire.dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'portalBatteryState' => $tenant?->portalDashboardBatteryState(),
            'onboarding' => $onboarding,
            'health' => $healthService->report(),
            'topScannedUnits' => $topScannedUnits->topForCurrentTenant(),
            'hasTimeModule' => $hasTimeModule,
            'canApplyStarterPack' => $canApplyStarterPack,
            'canManageStarterPack' => $canManageStarterPack,
            'starterPackSummary' => $starterPackSummary,
            'starterPackTypes' => TenantStarterPackType::cases(),
            'starterPackPreview' => $starterPackPreview,
            'starterPackUnitsHref' => $starterPackSummary !== null
                ? TenantOnboardingState::unitsOnboardingHref()
                : null,
        ]);
    }

    private function starterPackActor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function starterPackTenant(): Tenant
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant, 403);

        return $tenant;
    }

    private function resolveTenant(): ?Tenant
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        if ($user->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return $user->tenant;
    }
}
