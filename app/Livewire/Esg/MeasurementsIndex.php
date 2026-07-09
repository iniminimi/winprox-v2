<?php

declare(strict_types=1);

namespace App\Livewire\Esg;

use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Location;
use App\Models\Unit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class MeasurementsIndex extends Component
{
    use WithPagination;

    #[Url(as: 'indicator')]
    public ?int $indicatorFilter = null;

    #[Url(as: 'location')]
    public ?int $locationFilter = null;

    #[Url(as: 'unit')]
    public ?int $unitFilter = null;

    #[Url(as: 'from')]
    public string $recordedFrom = '';

    #[Url(as: 'to')]
    public string $recordedTo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', EsgMeasurement::class);
    }

    public function applyFilters(): void
    {
        $this->resetPage();

        $this->redirect(route('esg.measurements.index', array_filter([
            'indicator' => $this->indicatorFilter ?: null,
            'location' => $this->locationFilter ?: null,
            'unit' => $this->unitFilter ?: null,
            'from' => trim($this->recordedFrom) !== '' ? trim($this->recordedFrom) : null,
            'to' => trim($this->recordedTo) !== '' ? trim($this->recordedTo) : null,
        ])), navigate: true);
    }

    public function resetFilters(): void
    {
        $this->redirect(route('esg.measurements.index'), navigate: true);
    }

    public function render()
    {
        $this->authorize('viewAny', EsgMeasurement::class);

        $measurements = EsgMeasurement::query()
            ->with([
                'indicator:id,name,type,unit_of_measure,thresholds',
                'unit:id,name',
                'location:id,name',
                'worker:id,first_name,last_name',
            ])
            ->when($this->indicatorFilter, fn ($query) => $query->where('esg_indicator_id', $this->indicatorFilter))
            ->when($this->locationFilter, fn ($query) => $query->where('location_id', $this->locationFilter))
            ->when($this->unitFilter, fn ($query) => $query->where('unit_id', $this->unitFilter))
            ->when(trim($this->recordedFrom) !== '', fn ($query) => $query->whereDate('recorded_at', '>=', trim($this->recordedFrom)))
            ->when(trim($this->recordedTo) !== '', fn ($query) => $query->whereDate('recorded_at', '<=', trim($this->recordedTo)))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(25);

        return view('livewire.esg.measurements-index', [
            'measurements' => $measurements,
            'indicators' => EsgIndicator::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()
                ->when($this->locationFilter, fn ($query) => $query->where('location_id', $this->locationFilter))
                ->orderBy('name')
                ->get(['id', 'name', 'location_id']),
        ]);
    }
}
