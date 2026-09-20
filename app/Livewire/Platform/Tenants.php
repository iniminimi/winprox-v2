<?php

namespace App\Livewire\Platform;

use App\Actions\Platform\AssignTenantSubscriptionPlanAction;
use App\Actions\Platform\SetBillingUnitsCapAction;
use App\Actions\Platform\StartSupportViewAction;
use App\Actions\Platform\StopSupportViewAction;
use App\Actions\Platform\ToggleEsgModuleAction;
use App\Actions\Platform\ToggleIotModuleAction;
use App\Actions\Platform\TogglePresenceComplianceAction;
use App\Actions\Platform\ToggleTimeModuleAction;
use App\Actions\Platform\ToggleTrialApiAction;
use App\Actions\TenantPurge\CollectTenantPurgeCountsAction;
use App\Actions\TenantPurge\DeleteUnusedTenantAction;
use App\Enums\UnusedTenantDeletionReason;
use App\Http\Requests\Platform\AssignTenantSubscriptionPlanRequest;
use App\Http\Requests\Platform\DeleteUnusedTenantRequest;
use App\Http\Requests\Platform\SetBillingUnitsCapRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillingCatalogViewData;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Tenants extends Component
{
    use AuthorizesRequests;

    public string $search = '';

    /** @var array<int, string> */
    public array $unitsCapInputs = [];

    /** @var array<int, string> */
    public array $planInputs = [];

    public ?int $deleteTenantId = null;

    public string $deleteConfirmName = '';

    /** @var array<string, int> */
    public array $deleteCounts = [];

    public function mount(): void
    {
        $this->authorize('accessPlatform', User::class);
    }

    public function startSupport(int $tenantId, StartSupportViewAction $start): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $start->handle($tenant);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function stopSupport(StopSupportViewAction $stop): void
    {
        $stop->handle();

        $this->redirect(route('platform.tenants'));
    }

    public function toggleTrialApi(int $tenantId, ToggleTrialApiAction $toggle): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $toggle->handle($tenant, (int) auth()->id());
    }

    public function toggleEsgModule(int $tenantId, ToggleEsgModuleAction $toggle): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $toggle->handle($tenant, (int) auth()->id());
    }

    public function toggleIotModule(int $tenantId, ToggleIotModuleAction $toggle): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $toggle->handle($tenant, (int) auth()->id());
    }

    public function toggleTimeModule(int $tenantId, ToggleTimeModuleAction $toggle): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $toggle->handle($tenant, (int) auth()->id());
    }

    public function togglePresenceCompliance(int $tenantId, TogglePresenceComplianceAction $toggle): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $toggle->handle($tenant, (int) auth()->id());
    }

    public function saveUnitsCap(int $tenantId, SetBillingUnitsCapAction $action): void
    {
        $this->authorize('accessPlatform', User::class);

        $tenant = Tenant::query()->findOrFail($tenantId);
        $request = new SetBillingUnitsCapRequest;
        $validated = validator(
            ['units_cap' => (int) ($this->unitsCapInputs[$tenantId] ?? 0)],
            $request->rules(),
            $request->messages(),
        )->validate();

        try {
            $action->handle($tenant, (int) $validated['units_cap'], (int) auth()->id());
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'units_cap' => __('platform.errors.not_corporate'),
            ]);
        }

        session()->flash('success', __('platform.corporate_units_cap_saved'));
    }

    public function assignPlan(int $tenantId, AssignTenantSubscriptionPlanAction $action): void
    {
        $this->authorize('accessPlatform', User::class);

        $tenant = Tenant::query()->findOrFail($tenantId);
        $request = new AssignTenantSubscriptionPlanRequest;
        $plan = (string) ($this->planInputs[$tenantId] ?? '');
        $unitsCapRaw = $this->unitsCapInputs[$tenantId] ?? null;
        $validated = validator(
            [
                'plan' => $plan,
                'units_cap' => $unitsCapRaw === null || $unitsCapRaw === ''
                    ? null
                    : (int) $unitsCapRaw,
            ],
            $request::rules(),
            $request->messages(),
        )->validate();

        $planKey = (string) $validated['plan'];

        try {
            $action->handle(
                $tenant,
                $planKey,
                auth()->user(),
                $planKey === 'corporate' ? (int) ($validated['units_cap'] ?? 0) : null,
            );
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() === 'corporate_units_cap_required') {
                throw ValidationException::withMessages([
                    'units_cap' => __('platform.errors.units_cap_required'),
                ]);
            }

            throw $e;
        }

        $label = __("subscription.plans.{$planKey}.name");
        session()->flash('success', $planKey === 'corporate'
            ? __('platform.corporate_assigned', ['cap' => (int) $validated['units_cap']])
            : __('platform.plan_assigned', ['plan' => $label]));
    }

    /** @deprecated Use assignPlan */
    public function assignCorporate(int $tenantId, AssignTenantSubscriptionPlanAction $action): void
    {
        $this->planInputs[$tenantId] = 'corporate';
        $this->assignPlan($tenantId, $action);
    }

    public function openDeleteConfirm(int $tenantId, CollectTenantPurgeCountsAction $collectCounts): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $this->authorize('deleteUnusedTenant', $tenant);

        $this->deleteTenantId = $tenantId;
        $this->deleteConfirmName = '';
        $this->deleteCounts = array_filter($collectCounts->handle($tenant));
        $this->resetValidation();
    }

    public function closeDeleteConfirm(): void
    {
        $this->deleteTenantId = null;
        $this->deleteConfirmName = '';
        $this->deleteCounts = [];
        $this->resetValidation();
    }

    public function deleteTenant(DeleteUnusedTenantAction $delete): void
    {
        $tenant = Tenant::query()->findOrFail((int) $this->deleteTenantId);
        $this->authorize('deleteUnusedTenant', $tenant);

        $request = new DeleteUnusedTenantRequest;
        $this->validate($request->rules(), $request->messages());

        $name = $tenant->name;

        $delete->handle(
            tenant: $tenant,
            actor: auth()->user(),
            reason: UnusedTenantDeletionReason::SuperuserSpam,
            confirmName: $this->deleteConfirmName,
        );

        $this->closeDeleteConfirm();

        session()->flash('success', __('platform.tenants_delete.deleted', ['name' => $name]));
    }

    public function render()
    {
        $term = trim($this->search);

        $tenants = Tenant::query()
            ->withCount([
                'users',
                'users as verified_users_count' => function ($query): void {
                    $query->whereNotNull('email_verified_at');
                },
            ])
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $activeId = SupportTenantContext::activeTenantId();
        $activeTenant = $activeId !== null
            ? Tenant::query()->find($activeId)
            : null;

        foreach ($tenants as $tenant) {
            $id = (int) $tenant->id;
            if (! array_key_exists($id, $this->planInputs)) {
                $effective = $tenant->effectivePlanKey();
                $catalog = is_string($effective)
                    ? BillingCatalogViewData::catalogPlanFor($effective)
                    : null;
                $this->planInputs[$id] = in_array($catalog, BillingCatalogViewData::publicPlanKeys(), true)
                    ? $catalog
                    : 'winprox_10';
            }
        }

        return view('livewire.platform.tenants', [
            'tenants' => $tenants,
            'activeTenant' => $activeTenant,
            'assignablePlans' => BillingCatalogViewData::publicPlanKeys(),
        ]);
    }
}
