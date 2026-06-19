<?php

namespace App\Livewire\Locations;

use App\Models\Unit;
use App\Models\UnitGpsReport;
use Livewire\Attributes\On;
use Livewire\Component;

class UnitGpsHistoryModal extends Component
{
    public bool $show = false;

    public ?int $unitId = null;

    #[On('open-unit-gps-history')]
    public function open(int $unitId): void
    {
        $unit = Unit::query()->findOrFail($unitId);
        $this->authorize('view', $unit);

        $this->unitId = $unitId;
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->unitId = null;
    }

    public function render()
    {
        $unit = null;
        $reports = collect();

        if ($this->unitId !== null) {
            $unit = Unit::query()->with('translations')->find($this->unitId);
            if ($unit !== null) {
                $this->authorize('view', $unit);
                $reports = UnitGpsReport::query()
                    ->where('unit_id', $unit->id)
                    ->with('worker:id,first_name,last_name')
                    ->orderByDesc('reported_at')
                    ->orderByDesc('id')
                    ->get();
            }
        }

        return view('livewire.locations.unit-gps-history-modal', [
            'unit' => $unit,
            'reports' => $reports,
        ]);
    }
}
