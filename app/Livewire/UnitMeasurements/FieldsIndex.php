<?php

declare(strict_types=1);

namespace App\Livewire\UnitMeasurements;

use App\Actions\UnitMeasurements\SaveUnitMeasureFieldAction;
use App\Actions\UnitMeasurements\SetUnitMeasureFieldActiveAction;
use App\Enums\UnitMeasureFieldType;
use App\Http\Requests\UnitMeasurements\SaveUnitMeasureFieldRequest;
use App\Models\UnitMeasureField;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class FieldsIndex extends Component
{
    use AuthorizesRequests;

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
        $this->authorize('viewAny', UnitMeasureField::class);
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', UnitMeasureField::class);
        $this->resetForm();
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
            $save->handle(
                data: [
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
        return view('livewire.unit-measurements.fields-index', [
            'fields' => UnitMeasureField::query()->orderBy('name')->get(),
            'types' => UnitMeasureFieldType::cases(),
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
