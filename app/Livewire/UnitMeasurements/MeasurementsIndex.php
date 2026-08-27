<?php

declare(strict_types=1);

namespace App\Livewire\UnitMeasurements;

use App\Models\Location;
use App\Models\UnitMeasureField;
use App\Models\UnitMeasurement;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class MeasurementsIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    #[Url(as: 'field')]
    public ?int $fieldFilter = null;

    public function mount(): void
    {
        $this->authorize('viewAny', UnitMeasurement::class);
    }

    public function updatedLocationFilter(): void
    {
        $this->resetPage();
    }

    public function updatedFieldFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = UnitMeasurement::query()
            ->with(['unit', 'location', 'field', 'worker'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');

        if ($this->locationFilter !== null) {
            $query->where('location_id', $this->locationFilter);
        }

        if ($this->fieldFilter !== null) {
            $query->where('unit_measure_field_id', $this->fieldFilter);
        }

        return view('livewire.unit-measurements.measurements-index', [
            'measurements' => $query->paginate(25),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'address']),
            'fields' => UnitMeasureField::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
