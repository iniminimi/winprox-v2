<?php

declare(strict_types=1);

namespace App\Livewire\WorkVisits;

use App\Actions\Time\ListWorkVisitsAction;
use App\Models\Location;
use App\Models\Worker;
use App\Models\WorkVisit;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class WorkVisitsIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'worker')]
    public ?int $workerFilter = null;

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'from')]
    public string $from = '';

    #[Url(as: 'to')]
    public string $to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', WorkVisit::class);

        if ($this->from === '') {
            $this->from = now()->startOfMonth()->format('Y-m-d');
        }
        if ($this->to === '') {
            $this->to = now()->format('Y-m-d');
        }
    }

    public function applyFilters(): void
    {
        $this->resetPage();
    }

    public function render(ListWorkVisitsAction $list): View
    {
        $tenantId = (int) Tenancy::id();

        return view('livewire.work-visits.work-visits-index', [
            'visits' => $list->handle(
                $tenantId,
                from: $this->from !== '' ? $this->from : null,
                to: $this->to !== '' ? $this->to : null,
                workerId: $this->workerFilter,
                locationId: $this->locationFilter,
                status: $this->statusFilter !== '' ? $this->statusFilter : null,
            ),
            'workers' => Worker::query()->where('is_active', true)->orderBy('last_name')->orderBy('first_name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
        ]);
    }
}
