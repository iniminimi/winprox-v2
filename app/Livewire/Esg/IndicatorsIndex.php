<?php

namespace App\Livewire\Esg;

use App\Actions\Esg\CreateEsgIndicatorAction;
use App\Actions\Esg\SetEsgIndicatorActiveAction;
use App\Actions\Esg\UpdateEsgIndicatorAction;
use App\Enums\EsgIndicatorType;
use App\Http\Requests\Esg\StoreEsgIndicatorRequest;
use App\Models\EsgIndicator;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class IndicatorsIndex extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;

    public ?int $editingIndicatorId = null;

    public string $name = '';

    public string $type = 'numeric';

    public string $unitOfMeasure = '';

    public ?string $thresholdMin = null;

    public ?string $thresholdMax = null;

    /** @var list<string> */
    public array $choiceOptions = ['', ''];

    /** @var list<string> */
    public array $lockedChoiceOptions = [];

    public function mount(): void
    {
        $this->authorize('viewAny', EsgIndicator::class);
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', EsgIndicator::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $indicatorId): void
    {
        $indicator = EsgIndicator::query()->findOrFail($indicatorId);
        $this->authorize('update', $indicator);

        $this->editingIndicatorId = (int) $indicator->id;
        $this->name = (string) $indicator->name;
        $this->type = $indicator->type->value;
        $this->unitOfMeasure = (string) ($indicator->unit_of_measure ?? '');
        $this->thresholdMin = isset($indicator->thresholds['min'])
            ? (string) $indicator->thresholds['min']
            : null;
        $this->thresholdMax = isset($indicator->thresholds['max'])
            ? (string) $indicator->thresholds['max']
            : null;
        $this->choiceOptions = $indicator->type === EsgIndicatorType::Choice
            ? ($indicator->normalizedChoiceOptions() !== [] ? $indicator->normalizedChoiceOptions() : ['', ''])
            : ['', ''];
        $this->lockedChoiceOptions = $indicator->type === EsgIndicatorType::Choice
            ? $indicator->choiceOptionsWithMeasurements()
            : [];
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(
        CreateEsgIndicatorAction $create,
        UpdateEsgIndicatorAction $update,
    ): void {
        $tenantId = (int) Tenancy::id();
        $rules = StoreEsgIndicatorRequest::ruleSet(
            $tenantId,
            $this->editingIndicatorId,
        );

        $validated = $this->validate([
            'name' => $rules['name'],
            'type' => $rules['type'],
            'unitOfMeasure' => $rules['unit_of_measure'],
            'thresholdMin' => $rules['threshold_min'],
            'thresholdMax' => $rules['threshold_max'],
        ], [
            'name.required' => __('esg.errors.name_required'),
            'name.unique' => __('esg.errors.duplicate_name'),
            'type.required' => __('esg.errors.type_required'),
        ]);

        try {
            $existing = $this->editingIndicatorId !== null
                ? EsgIndicator::query()->find($this->editingIndicatorId)
                : null;

            $payload = StoreEsgIndicatorRequest::toActionPayload([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'unit_of_measure' => $validated['unitOfMeasure'] ?: null,
                'threshold_min' => $validated['thresholdMin'],
                'threshold_max' => $validated['thresholdMax'],
                'choice_options' => $validated['type'] === EsgIndicatorType::Choice->value
                    ? $this->choiceOptions
                    : [],
            ], $existing);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addError($field, $message);
                }
            }

            return;
        }

        if ($this->editingIndicatorId === null) {
            $this->authorize('create', EsgIndicator::class);
            $create->handle($tenantId, $payload, (int) auth()->id());
            session()->flash('success', __('esg.flash.created'));
        } else {
            $indicator = EsgIndicator::query()->findOrFail($this->editingIndicatorId);
            $this->authorize('update', $indicator);
            $update->handle($indicator, $payload, (int) auth()->id());
            session()->flash('success', __('esg.flash.updated'));
        }

        $this->closeModal();
    }

    public function toggleActive(int $indicatorId, SetEsgIndicatorActiveAction $setActive): void
    {
        $indicator = EsgIndicator::query()->findOrFail($indicatorId);
        $this->authorize('deactivate', $indicator);

        $activate = ! $indicator->is_active;
        $setActive->handle($indicator, $activate, (int) auth()->id());

        session()->flash(
            'success',
            $activate ? __('esg.flash.activated') : __('esg.flash.deactivated'),
        );
    }

    public function addChoiceOption(): void
    {
        $this->choiceOptions[] = '';
    }

    public function removeChoiceOption(int $index): void
    {
        if (count($this->choiceOptions) <= 2) {
            return;
        }

        $option = trim((string) ($this->choiceOptions[$index] ?? ''));
        if ($option !== '' && in_array($option, $this->lockedChoiceOptions, true)) {
            return;
        }

        unset($this->choiceOptions[$index]);
        $this->choiceOptions = array_values($this->choiceOptions);
    }

    public function updatedType(string $value): void
    {
        if ($value === EsgIndicatorType::Choice->value && $this->choiceOptions === []) {
            $this->choiceOptions = ['', ''];
        }
    }

    public function render()
    {
        return view('livewire.esg.indicators-index', [
            'indicators' => EsgIndicator::query()->orderBy('name')->get(),
            'types' => EsgIndicatorType::cases(),
        ]);
    }

    private function resetForm(): void
    {
        $this->editingIndicatorId = null;
        $this->name = '';
        $this->type = EsgIndicatorType::Numeric->value;
        $this->unitOfMeasure = '';
        $this->thresholdMin = null;
        $this->thresholdMax = null;
        $this->choiceOptions = ['', ''];
        $this->lockedChoiceOptions = [];
    }
}
