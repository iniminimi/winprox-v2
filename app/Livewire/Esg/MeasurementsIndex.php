<?php

declare(strict_types=1);

namespace App\Livewire\Esg;

use App\Actions\Esg\ListEsgMeasurementsAction;
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

    #[Url(as: 'alarms')]
    public bool $alarmsOnly = false;

    public bool $showCorrectionModal = false;

    public ?int $correctingMeasurementId = null;

    public ?string $correctionValueNumeric = null;

    public ?string $correctionValueBoolean = null;

    public string $correctionValueString = '';

    public string $correctionValueJson = '';

    /** @var list<string> */
    public array $correctionValueMultiChoice = [];

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
            'alarms' => $this->alarmsOnly ? 1 : null,
        ])), navigate: true);
    }

    public function resetFilters(): void
    {
        $this->redirect(route('esg.measurements.index'), navigate: true);
    }

    public function updatedLocationFilter(): void
    {
        $this->resetUnitFilterIfInvalid();
    }

    public function updatedIndicatorFilter(): void
    {
        $this->resetUnitFilterIfInvalid();
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
            'correctionValueMultiChoice' => $this->correctionValueMultiChoice,
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

    public function render(ListEsgMeasurementsAction $listMeasurements)
    {
        $this->authorize('viewAny', EsgMeasurement::class);

        $measurements = $listMeasurements->handle(
            tenantId: (int) Tenancy::id(),
            indicatorId: $this->indicatorFilter,
            locationId: $this->locationFilter,
            unitId: $this->unitFilter,
            recordedFrom: trim($this->recordedFrom) !== '' ? trim($this->recordedFrom) : null,
            recordedTo: trim($this->recordedTo) !== '' ? trim($this->recordedTo) : null,
            alarmsOnly: $this->alarmsOnly,
        );

        $hasFilters = $this->indicatorFilter !== null
            || $this->locationFilter !== null
            || $this->unitFilter !== null
            || trim($this->recordedFrom) !== ''
            || trim($this->recordedTo) !== ''
            || $this->alarmsOnly;

        return view('livewire.esg.measurements-index', [
            'measurements' => $measurements,
            'showSetupSteps' => ! $hasFilters && ! EsgMeasurement::query()->exists(),
            'correctingMeasurement' => $this->correctingMeasurementId !== null
                ? EsgMeasurement::query()->with(['indicator.translations'])->find($this->correctingMeasurementId)
                : null,
            'indicators' => EsgIndicator::query()->with('translations')->orderBy('name')->get(['id', 'name', 'original_language']),
            'locations' => Location::query()->with('translations')->orderBy('name')->get(['id', 'name', 'original_language']),
            'units' => $this->filterableUnits(),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Unit> */
    private function filterableUnits()
    {
        $unitIds = $this->filterableUnitIds();

        if ($unitIds === []) {
            return collect();
        }

        return Unit::query()
            ->with('translations')
            ->whereIn('id', $unitIds)
            ->orderBy('name')
            ->get(['id', 'name', 'location_id', 'original_language']);
    }

    /** @return list<int> */
    private function filterableUnitIds(): array
    {
        return EsgMeasurement::query()
            ->when($this->indicatorFilter, fn ($query) => $query->where('esg_indicator_id', $this->indicatorFilter))
            ->when($this->locationFilter, fn ($query) => $query->where('location_id', $this->locationFilter))
            ->whereNotNull('unit_id')
            ->distinct()
            ->pluck('unit_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function resetUnitFilterIfInvalid(): void
    {
        if ($this->unitFilter === null) {
            return;
        }

        if (! in_array($this->unitFilter, $this->filterableUnitIds(), true)) {
            $this->unitFilter = null;
        }
    }

    private function resetCorrectionFields(): void
    {
        $this->correctionValueNumeric = null;
        $this->correctionValueBoolean = null;
        $this->correctionValueString = '';
        $this->correctionValueJson = '';
        $this->correctionValueMultiChoice = [];
        $this->correctionRecordedAt = '';
    }
}
