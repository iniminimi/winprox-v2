<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Actions\Audit\ListTenantAuditLogsAction;
use App\Models\Tenant;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Activity extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant, 404);

        $this->authorize('manageOrganisation', $tenant);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(ListTenantAuditLogsAction $list)
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant, 404);

        $result = $list->handle((int) $tenant->id, $this->search, $this->getPage());

        return view('livewire.pages.activity', [
            'logs' => $result['rows'],
            'summaries' => $result['summaries'],
        ]);
    }

    private function resolveTenant(): ?Tenant
    {
        if (auth()->user()?->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return auth()->user()?->tenant;
    }
}
