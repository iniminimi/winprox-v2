<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Worker;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class WorkersIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    #[Url(as: 'active')]
    public string $activeFilter = 'all';

    public function mount(): void
    {
        $this->authorize('viewAny', Worker::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTeamFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedActiveFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $user = auth()->user();
        $scopeLocationIds = $user?->accessibleLocationIds();

        $locationsQuery = Location::query()->orderBy('name');
        if ($scopeLocationIds !== null) {
            $locationsQuery->whereIn('id', $scopeLocationIds);
        }
        $locations = $locationsQuery->get(['id', 'name', 'address']);

        $teams = InternalTeam::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        $query = Worker::query()
            ->with(['team', 'locations', 'user'])
            ->when(trim($this->search) !== '', function ($builder) {
                $term = '%'.trim($this->search).'%';
                $builder->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($this->teamFilter !== null, fn ($builder) => $builder->where('internal_team_id', $this->teamFilter))
            ->when($this->activeFilter === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($this->activeFilter === 'inactive', fn ($builder) => $builder->where('is_active', false))
            ->when($this->locationFilter !== null, function ($builder) {
                $locationId = $this->locationFilter;
                $builder->where(function ($inner) use ($locationId) {
                    $inner->whereHas('team', fn ($teamQuery) => $teamQuery->where('clocks_all_locations', true))
                        ->orWhereHas('locations', fn ($locationQuery) => $locationQuery->where('locations.id', $locationId))
                        ->orWhereDoesntHave('locations');
                });
            })
            ->when($scopeLocationIds !== null, function ($builder) use ($scopeLocationIds) {
                $builder->where(function ($inner) use ($scopeLocationIds) {
                    $inner->whereHas('team', fn ($teamQuery) => $teamQuery->where('clocks_all_locations', true))
                        ->orWhereHas('locations', fn ($locationQuery) => $locationQuery->whereIn('locations.id', $scopeLocationIds))
                        ->orWhereDoesntHave('locations');
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name');

        $workers = $query->paginate(50);

        return view('livewire.pages.workers-index', [
            'workers' => $workers,
            'locations' => $locations,
            'teams' => $teams,
        ]);
    }
}
