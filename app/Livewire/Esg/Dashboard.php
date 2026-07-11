<?php

declare(strict_types=1);

namespace App\Livewire\Esg;

use App\Actions\Esg\BuildEsgDashboardAction;
use App\Models\EsgMeasurement;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Dashboard extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewAny', EsgMeasurement::class);
    }

    public function render(BuildEsgDashboardAction $buildDashboard): mixed
    {
        $tenantId = Tenancy::id() ?? (int) auth()->user()->tenant_id;

        return view('livewire.esg.dashboard', [
            'dashboard' => $buildDashboard->handle($tenantId),
        ]);
    }
}
