<?php

declare(strict_types=1);

namespace App\Livewire\Pages;

use App\Enums\UnitCheckResult;
use App\Models\Location;
use App\Models\UnitCheck;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class UnitChecksIndex extends Component
{
    use WithPagination;

    #[Url(as: 'result')]
    public string $resultFilter = 'all';

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    public function mount(): void
    {
        $this->authorize('viewAny', UnitCheck::class);

        if (! in_array($this->resultFilter, ['all', ...UnitCheckResult::values()], true)) {
            $this->resultFilter = 'all';
        }
    }

    public function updatedResultFilter(): void
    {
        $this->resetPage();
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = UnitCheck::query()
            ->with(['unit', 'location', 'worker', 'team'])
            ->orderByDesc('checked_at')
            ->orderByDesc('id');

        if ($this->resultFilter !== 'all') {
            $query->where('result', $this->resultFilter);
        }

        if ($this->locationFilter !== null) {
            $query->where('location_id', $this->locationFilter);
        }

        return view('livewire.pages.unit-checks-index', [
            'checks' => $query->paginate(25),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'address']),
        ]);
    }
}
