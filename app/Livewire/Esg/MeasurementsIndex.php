<?php

declare(strict_types=1);

namespace App\Livewire\Esg;

use App\Actions\Esg\RecordEsgMeasurementCorrectionAction;
use App\Http\Requests\Esg\RecordEsgMeasurementCorrectionRequest;
use App\Models\EsgIndicator;
use App\Models\EsgMeasurement;
use App\Models\Location;
use App\Models\Unit;
use App\Support\Tenancy;
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

    public bool $showCorrectionModal = false;

    public ?int $correctingMeasurementId = null;

    public ?string $correctionValueNumeric = null;

    public ?string $correctionValueBoolean = null;

    public string $correctionValueString = '';

    public string $correctionValueJson = '';

    public string $correctionRecordedAt = '';

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

    public function openCorrectionModal(int $measurementId): void
    {
        $measurement = EsgMeasurement::query()
            ->with('indicator')
            ->findOrFail($measurementId);

        $this->authorize('correct', $measurement);

        $this->correctingMeasurementId = $measurement->id;
        $this->resetCorrectionFields();
        $this->correctionRecordedAt = now()->format('Y-m-d\TH:i');
        $this->showCorrectionModal = true;
    }

    public function closeCorrectionModal(): void
    {
        $this->showCorrectionModal = false;
        $this->correctingMeasurementId = null;
        $this->resetCorrectionFields();
    }

    public function saveCorrection(RecordEsgMeasurementCorrectionAction $recordCorrection): void
    {
        if ($this->correctingMeasurementId === null) {
            return;
        }

        $original = EsgMeasurement::query()
            ->with('indicator')
            ->findOrFail($this->correctingMeasurementId);

        $this->authorize('correct', $original);

        $indicator = $original->indicator;
        if ($indicator === null) {
            return;
        }

        $this->validate(
            array_merge(
                ['correctionRecordedAt' => ['required', 'date']],
                RecordEsgMeasurementCorrectionRequest::valueRules($indicator->type),
            ),
            RecordEsgMeasurementCorrectionRequest::validationMessages($indicator->type),
        );

        $validated = RecordEsgMeasurementCorrectionRequest::livewireToValidated($original, [
            'correctionValueNumeric' => $this->correctionValueNumeric,
            'correctionValueBoolean' => $this->correctionValueBoolean,
            'correctionValueString' => $this->correctionValueString,
            'correctionValueJson' => $this->correctionValueJson,
            'correctionRecordedAt' => $this->correctionRecordedAt,
        ]);

        $data = RecordEsgMeasurementCorrectionRequest::toData($original, $validated);

        $recordCorrection->handle(
            $original,
            $data,
            (int) Tenancy::id(),
            (int) auth()->id(),
        );

        session()->flash('success', __('esg.flash.correction_recorded'));
        $this->closeCorrectionModal();
    }

    public function render()
    {
        $this->authorize('viewAny', EsgMeasurement::class);

        $measurements = EsgMeasurement::query()
            ->with([
                'indicator:id,name,type,unit_of_measure,thresholds,options',
                'unit:id,name',
                'location:id,name',
                'worker:id,first_name,last_name',
                'correctsMeasurement.indicator:id,name,type,unit_of_measure,thresholds,options',
            ])
            ->when($this->indicatorFilter, fn ($query) => $query->where('esg_indicator_id', $this->indicatorFilter))
            ->when($this->locationFilter, fn ($query) => $query->where('location_id', $this->locationFilter))
            ->when($this->unitFilter, fn ($query) => $query->where('unit_id', $this->unitFilter))
            ->when(trim($this->recordedFrom) !== '', fn ($query) => $query->whereDate('recorded_at', '>=', trim($this->recordedFrom)))
            ->when(trim($this->recordedTo) !== '', fn ($query) => $query->whereDate('recorded_at', '<=', trim($this->recordedTo)))
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(25);

        $hasFilters = $this->indicatorFilter !== null
            || $this->locationFilter !== null
            || $this->unitFilter !== null
            || trim($this->recordedFrom) !== ''
            || trim($this->recordedTo) !== '';

        return view('livewire.esg.measurements-index', [
            'measurements' => $measurements,
            'showSetupSteps' => ! $hasFilters && ! EsgMeasurement::query()->exists(),
            'correctingMeasurement' => $this->correctingMeasurementId !== null
                ? EsgMeasurement::query()->with('indicator')->find($this->correctingMeasurementId)
                : null,
            'indicators' => EsgIndicator::query()->orderBy('name')->get(['id', 'name']),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'units' => Unit::query()
                ->when($this->locationFilter, fn ($query) => $query->where('location_id', $this->locationFilter))
                ->orderBy('name')
                ->get(['id', 'name', 'location_id']),
        ]);
    }

    private function resetCorrectionFields(): void
    {
        $this->correctionValueNumeric = null;
        $this->correctionValueBoolean = null;
        $this->correctionValueString = '';
        $this->correctionValueJson = '';
        $this->correctionRecordedAt = '';
    }
}
