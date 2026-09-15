<?php

namespace App\Livewire\Time;

use App\Actions\Time\ListShiftTypesAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Actions\Time\SetShiftTypeActiveAction;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Exceptions\RosterValidationException;
use App\Http\Requests\Time\SaveShiftTypeRequest;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ShiftTypesIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;

    public bool $showModal = false;
    public ?int $editingTypeId = null;
    public string $typeCode = '';
    public string $typeLabel = '';
    public string $typeStart = '07:00';
    public string $typeEnd = '15:00';
    public int $typeBreak = 0;
    public string $typeColor = 'none';
    public string $typeKind = 'work';

    public function mount(): void
    {
        $this->authorize('viewAny', ShiftType::class);
    }

    public function openCreate(): void
    {
        $this->authorize('create', ShiftType::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $typeId): void
    {
        $type = ShiftType::query()->findOrFail($typeId);
        $this->authorize('update', $type);
        $this->editingTypeId = $type->id;
        $this->typeCode = $type->code;
        $this->typeLabel = $type->label;
        $this->typeStart = $type->start_time ?? '07:00';
        $this->typeEnd = $type->end_time ?? '15:00';
        $this->typeBreak = (int) $type->break_minutes;
        $this->typeColor = $type->color->value;
        $this->typeKind = $type->kind->value;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(SaveShiftTypeAction $save): void
    {
        $rules = SaveShiftTypeRequest::rulesFor();
        $fieldRules = [
            'typeCode' => $rules['code'],
            'typeLabel' => $rules['label'],
            'typeKind' => $rules['kind'],
            'typeColor' => $rules['color'],
        ];
        if ($this->typeKind === ShiftTypeKind::Work->value) {
            $fieldRules['typeStart'] = $rules['start_time'];
            $fieldRules['typeEnd'] = $rules['end_time'];
            $fieldRules['typeBreak'] = $rules['break_minutes'];
        }

        $validated = $this->validate($fieldRules, [], [
            'typeCode' => __('time.schedule.types.code'),
            'typeLabel' => __('time.schedule.types.label'),
            'typeKind' => __('time.schedule.types.kind'),
            'typeStart' => __('time.schedule.types.start'),
            'typeEnd' => __('time.schedule.types.end'),
            'typeBreak' => __('time.schedule.types.break'),
            'typeColor' => __('time.schedule.types.color'),
        ]);

        $color = ShiftTypeColor::from($validated['typeColor']);
        $kind = ShiftTypeKind::from($validated['typeKind']);

        try {
            if ($this->editingTypeId) {
                $type = ShiftType::query()->findOrFail($this->editingTypeId);
                $this->authorize('update', $type);
                $save->handle(
                    Tenant::query()->findOrFail(Tenancy::id()),
                    new SaveShiftTypeData(
                        code: $validated['typeCode'],
                        label: $validated['typeLabel'],
                        startTime: $kind->isWork() ? $validated['typeStart'] : null,
                        endTime: $kind->isWork() ? $validated['typeEnd'] : null,
                        breakMinutes: $kind->isWork() ? (int) $validated['typeBreak'] : 0,
                        color: $color,
                        isActive: $type->is_active,
                        kind: $kind,
                    ),
                    auth()->id(),
                    $type->id,
                );
            } else {
                $this->authorize('create', ShiftType::class);
                $save->handle(
                    Tenant::query()->findOrFail(Tenancy::id()),
                    new SaveShiftTypeData(
                        code: $validated['typeCode'],
                        label: $validated['typeLabel'],
                        startTime: $kind->isWork() ? $validated['typeStart'] : null,
                        endTime: $kind->isWork() ? $validated['typeEnd'] : null,
                        breakMinutes: $kind->isWork() ? (int) $validated['typeBreak'] : 0,
                        color: $color,
                        isActive: true,
                        kind: $kind,
                    ),
                    auth()->id(),
                );
            }
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->addError('typeCode', __($e->getMessage()));

            return;
        }

        $this->closeModal();
        session()->flash('time_flash', __('time.schedule.types.saved'));
    }

    public function toggleActive(int $typeId, SetShiftTypeActiveAction $setActive): void
    {
        $type = ShiftType::query()->findOrFail($typeId);
        $this->authorize('update', $type);
        $setActive->handle($type, ! $type->is_active, auth()->id());
    }

    public function render(ListShiftTypesAction $listTypes)
    {
        return view('livewire.time.shift-types-index', [
            'shiftTypes' => $listTypes->handle((int) Tenancy::id(), false),
            'colors' => ShiftTypeColor::cases(),
            'kinds' => ShiftTypeKind::cases(),
            'alarmCount' => $this->timeNavAlarmCount(),
        ]);
    }

    private function resetForm(): void
    {
        $this->editingTypeId = null;
        $this->typeCode = '';
        $this->typeLabel = '';
        $this->typeStart = '07:00';
        $this->typeEnd = '15:00';
        $this->typeBreak = 0;
        $this->typeColor = ShiftTypeColor::default()->value;
        $this->typeKind = ShiftTypeKind::Work->value;
        $this->resetErrorBag();
    }
}
