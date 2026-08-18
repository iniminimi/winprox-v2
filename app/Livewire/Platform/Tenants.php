<?php

namespace App\Livewire\Platform;

use App\Actions\Platform\AssignCorporateSubscriptionAction;
use App\Actions\Platform\SetBillingUnitsCapAction;
use App\Actions\Platform\StartSupportViewAction;
use App\Actions\Platform\StopSupportViewAction;
use App\Actions\Platform\ToggleEsgModuleAction;
use App\Actions\Platform\ToggleIotModuleAction;
use App\Actions\Platform\ToggleTimeModuleAction;
use App\Actions\Platform\ToggleTrialApiAction;
use App\Http\Requests\Platform\AssignCorporateSubscriptionRequest;
use App\Http\Requests\Platform\SetBillingUnitsCapRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
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

    public function assignCorporate(int $tenantId, AssignCorporateSubscriptionAction $action): void
    {
        $this->authorize('accessPlatform', User::class);

        $tenant = Tenant::query()->findOrFail($tenantId);
        $request = new AssignCorporateSubscriptionRequest;
        $validated = validator(
            ['units_cap' => (int) ($this->unitsCapInputs[$tenantId] ?? 0)],
            $request->rules(),
            $request->messages(),
        )->validate();

        $action->handle($tenant, (int) $validated['units_cap'], auth()->user());

        session()->flash('success', __('platform.corporate_assigned', ['cap' => $validated['units_cap']]));
    }

    public function render()
    {
        $term = trim($this->search);

        $tenants = Tenant::query()
            ->when($term !== '', function ($query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like);
            })
            ->orderBy('name')
            ->limit(100)
            ->get();

        $activeId = SupportTenantContext::activeTenantId();
        $activeTenant = $activeId !== null
            ? Tenant::query()->find($activeId)
            : null;

        return view('livewire.platform.tenants', [
            'tenants' => $tenants,
            'activeTenant' => $activeTenant,
        ]);
    }
}
