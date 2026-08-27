<?php

declare(strict_types=1);

namespace App\Livewire\UnitMeasurements;

use App\Actions\UnitMeasurements\SaveUnitMeasureFieldAction;
use App\Actions\UnitMeasurements\SetUnitMeasureFieldActiveAction;
use App\Enums\UnitMeasureFieldType;
use App\Http\Requests\UnitMeasurements\SaveUnitMeasureFieldRequest;
use App\Models\Location;
use App\Models\UnitMeasureField;
use App\Models\UnitMeasurement;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
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
    public string $locationFilter = '';

    #[Url(as: 'field')]
    public string $fieldFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    public bool $fieldsOpen = false;

    public bool $showModal = false;

    public ?int $editingFieldId = null;

    public string $name = '';

    public string $type = 'numeric';

    public string $unitOfMeasure = '';

    public ?string $minValue = null;

    public ?string $maxValue = null;

    /** @var list<string> */
    public array $choiceOptions = ['', ''];

    /** @var list<string> */
    public array $lockedChoiceOptions = [];

    public bool $editingHasMeasurements = false;

    public function mount(): void
    {
        $this->authorize('viewAny', UnitMeasurement::class);
        $this->fieldsOpen = ! UnitMeasureField::query()->exists();
    }

    public function applyFilters(): void
    {
        $this->redirect(route('unit-measurements.index', array_filter([
            'location' => $this->locationFilter !== '' ? $this->locationFilter : null,
            'field' => $this->fieldFilter !== '' ? $this->fieldFilter : null,
            'q' => trim($this->search) !== '' ? trim($this->search) : null,
        ], fn ($value) => $value !== null && $value !== '')), navigate: true);
    }

    public function resetFilters(): void
    {
        $this->redirect(route('unit-measurements.index'), navigate: true);
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', UnitMeasureField::class);
        $this->resetForm();
        $this->fieldsOpen = true;
        $this->showModal = true;
    }

    public function openEditModal(int $fieldId): void
    {
        $field = UnitMeasureField::query()->findOrFail($fieldId);
        $this->authorize('update', $field);

        $this->editingFieldId = (int) $field->id;
        $this->name = (string) $field->name;
        $this->type = $field->type->value;
        $this->unitOfMeasure = (string) ($field->unit_of_measure ?? '');
        $this->minValue = $field->min_value !== null ? (string) $field->min_value : null;
        $this->maxValue = $field->max_value !== null ? (string) $field->max_value : null;
        $this->choiceOptions = $field->type->usesOptionList()
            ? ($field->normalizedChoiceOptions() !== [] ? $field->normalizedChoiceOptions() : ['', ''])
            : ['', ''];
        $this->lockedChoiceOptions = $field->type->usesOptionList()
            ? $field->choiceOptionsWithMeasurements()
            : [];
        $this->editingHasMeasurements = $field->hasMeasurements();
        $this->fieldsOpen = true;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function addChoiceOption(): void
    {
        if (count($this->choiceOptions) >= 30) {
            return;
        }
        $this->choiceOptions[] = '';
    }

    public function removeChoiceOption(int $index): void
    {
        $option = $this->choiceOptions[$index] ?? null;
        if (is_string($option) && in_array($option, $this->lockedChoiceOptions, true)) {
            return;
        }
        unset($this->choiceOptions[$index]);
        $this->choiceOptions = array_values($this->choiceOptions);
        if ($this->choiceOptions === []) {
            $this->choiceOptions = ['', ''];
        }
    }

    public function save(SaveUnitMeasureFieldAction $save): void
    {
        $rules = SaveUnitMeasureFieldRequest::staticRules();
        $validateRules = [
            'name' => $rules['name'],
            'type' => $rules['type'],
            'unitOfMeasure' => $rules['unit_of_measure'],
            'minValue' => $rules['min_value'],
            'maxValue' => $rules['max_value'],
        ];

        if ($this->type === UnitMeasureFieldType::Choice->value) {
            $validateRules['choiceOptions'] = $rules['options'];
            $validateRules['choiceOptions.*'] = $rules['options.*'];
        }

        $validated = $this->validate($validateRules);

        $existing = $this->editingFieldId !== null
            ? UnitMeasureField::query()->findOrFail($this->editingFieldId)
            : null;

        if ($existing !== null) {
            $this->authorize('update', $existing);
        } else {
            $this->authorize('create', UnitMeasureField::class);
        }

        try {
            $dto = SaveUnitMeasureFieldRequest::toData(
                [
                    'name' => $validated['name'],
                    'type' => $validated['type'],
                    'unit_of_measure' => $validated['unitOfMeasure'] ?? null,
                    'min_value' => $validated['minValue'] ?? null,
                    'max_value' => $validated['maxValue'] ?? null,
                    'options' => $this->type === UnitMeasureFieldType::Choice->value
                        ? ($validated['choiceOptions'] ?? [])
                        : [],
                    'is_active' => $existing?->is_active ?? true,
                ],
                tenantId: (int) Tenancy::id(),
                field: $existing,
            );

            $save->handle(
                data: $dto,
                tenantId: (int) Tenancy::id(),
                field: $existing,
                actorUserId: auth()->id() ? (int) auth()->id() : null,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $livewireKey = match ($key) {
                    'unit_of_measure' => 'unitOfMeasure',
                    'min_value' => 'minValue',
                    'max_value' => 'maxValue',
                    'options' => 'choiceOptions',
                    default => $key,
                };
                foreach ($messages as $message) {
                    $this->addError($livewireKey, $message);
                }
            }

            return;
        }

        session()->flash('success', __('unit_measurements.flash.field_saved'));
        $this->closeModal();
    }

    public function toggleActive(int $fieldId, SetUnitMeasureFieldActiveAction $setActive): void
    {
        $field = UnitMeasureField::query()->findOrFail($fieldId);
        $this->authorize('update', $field);
        $setActive->handle($field, ! $field->is_active, auth()->id() ? (int) auth()->id() : null);
    }

    public function render(): View
    {
        $measureFields = UnitMeasureField::query()->orderBy('name')->get();

        $query = UnitMeasurement::query()
            ->with(['unit', 'location', 'field', 'worker'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id');

        if ($this->locationFilter !== '') {
            $query->where('location_id', (int) $this->locationFilter);
        }

        if ($this->fieldFilter !== '') {
            $query->where('unit_measure_field_id', (int) $this->fieldFilter);
        }

        $search = trim($this->search);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like): void {
                $builder->where('value_string', 'like', $like)
                    ->orWhereHas('field', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('unit', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('location', function ($q) use ($like): void {
                        $q->where('name', 'like', $like)->orWhere('address', 'like', $like);
                    });
            });
        }

        $hasFilters = $this->locationFilter !== '' || $this->fieldFilter !== '' || trim($this->search) !== '';
        $total = (clone $query)->count();

        return view('livewire.unit-measurements.measurements-index', [
            'measureFields' => $measureFields,
            'types' => UnitMeasureFieldType::cases(),
            'measurements' => $query->paginate(25),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'address']),
            'filterFields' => $measureFields,
            'hasFilters' => $hasFilters,
            'total' => $total,
            'exportUrl' => route('unit-measurements.export', array_filter([
                'location' => $this->locationFilter !== '' ? $this->locationFilter : null,
                'field' => $this->fieldFilter !== '' ? $this->fieldFilter : null,
                'q' => trim($this->search) !== '' ? trim($this->search) : null,
            ])),
            'printUrl' => route('unit-measurements.print', array_filter([
                'location' => $this->locationFilter !== '' ? $this->locationFilter : null,
                'field' => $this->fieldFilter !== '' ? $this->fieldFilter : null,
                'q' => trim($this->search) !== '' ? trim($this->search) : null,
            ])),
        ]);
    }

    private function resetForm(): void
    {
        $this->editingFieldId = null;
        $this->name = '';
        $this->type = 'numeric';
        $this->unitOfMeasure = '';
        $this->minValue = null;
        $this->maxValue = null;
        $this->choiceOptions = ['', ''];
        $this->lockedChoiceOptions = [];
        $this->editingHasMeasurements = false;
        $this->resetErrorBag();
    }
}
