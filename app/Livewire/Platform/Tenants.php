<?php

namespace App\Livewire\Platform;

use App\Actions\Platform\StartSupportViewAction;
use App\Actions\Platform\StopSupportViewAction;
use App\Actions\Platform\ToggleEsgModuleAction;
use App\Actions\Platform\ToggleTrialApiAction;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Tenants extends Component
{
    use AuthorizesRequests;

    public string $search = '';

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
