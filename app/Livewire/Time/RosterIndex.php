<?php

namespace App\Livewire\Time;

use App\Actions\Time\CopyWeekAction;
use App\Actions\Time\ListRosterWeekAction;
use App\Actions\Time\ListShiftTypesAction;
use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\ResolveRosterWeekAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Actions\Time\SetShiftTypeActiveAction;
use App\Data\Time\CopyWeekData;
use App\Data\Time\PublishWeekData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\ShiftTypeColor;
use App\Exceptions\RosterValidationException;
use App\Http\Requests\Time\CopyWeekRequest;
use App\Http\Requests\Time\PublishWeekRequest;
use App\Http\Requests\Time\SavePlannedShiftsRequest;
use App\Http\Requests\Time\SaveShiftTypeRequest;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Support\Tenancy;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class RosterIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;

    #[Url(as: 'week')]
    public string $weekStart = '';

    #[Url(as: 'team')]
    public ?int $teamFilter = null;

    public bool $showTypeModal = false;
    public ?int $editingTypeId = null;
    public ?int $selectedTypeId = null;
    public string $typeCode = '';
    public string $typeLabel = '';
    public string $typeStart = '07:00';
    public string $typeEnd = '15:00';
    public int $typeBreak = 0;
    public string $typeColor = 'none';

    public function mount(ResolveRosterWeekAction $resolveWeek): void
    {
        $this->authorize('viewAny', PlannedShift::class);

        if ($this->weekStart === '') {
            $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        } else {
            [$monday] = $resolveWeek->handle($this->weekStart);
            $this->weekStart = $monday->toDateString();
        }
    }

    public function previousWeek(ResolveRosterWeekAction $resolveWeek): void
    {
        [$monday] = $resolveWeek->handle($this->weekStart);
        $this->weekStart = $monday->subWeek()->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function nextWeek(ResolveRosterWeekAction $resolveWeek): void
    {
        [$monday] = $resolveWeek->handle($this->weekStart);
        $this->weekStart = $monday->addWeek()->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function thisWeek(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->dispatch('roster-week-changed');
    }

    public function updatedTeamFilter(mixed $value): void
    {
        $this->teamFilter = $value === '' || $value === null ? null : (int) $value;
        $this->dispatch('roster-week-changed');
    }

    public function updatedSelectedTypeId(mixed $value): void
    {
        $this->selectedTypeId = $value === '' || $value === null ? null : (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ListRosterWeekAction $list): array
    {
        $this->authorize('viewAny', PlannedShift::class);
        $snapshot = $list->handle(
            (int) Tenancy::id(),
            $this->weekStart,
            $this->teamFilter,
            auth()->user(),
        );

        $array = $snapshot->toArray();
        $array['invalid_message'] = __('time.schedule.errors.invalid_cells');

        return $array;
    }

    /**
     * @param  list<array{worker_id: int, date: string, raw: ?string}>  $cells
     */
    public function save(array $cells, SavePlannedShiftsAction $save): void
    {
        $this->authorize('update', PlannedShift::class);
        $payload = $this->payload(app(ListRosterWeekAction::class));
        $workerIds = array_map(fn ($worker) => (int) $worker['id'], $payload['workers']);

        $normalized = [];
        foreach ($cells as $cell) {
            $normalized[] = [
                'worker_id' => (int) $cell['worker_id'],
                'date' => (string) $cell['date'],
                'raw' => (string) ($cell['raw'] ?? ''),
            ];
        }

        Validator::make(
            [
                'week_start' => $this->weekStart,
                'worker_ids' => $workerIds,
                'cells' => $normalized,
            ],
            SavePlannedShiftsRequest::rulesFor(),
        )->validate();

        try {
            $save->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new SavePlannedShiftsData($this->weekStart, $workerIds, $normalized),
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->dispatch('roster-save-failed', message: __($e->getMessage()), cells: $e instanceof RosterValidationException ? $e->cells : []);

            return;
        }

        session()->flash('time_flash', __('time.schedule.saved'));
        $this->dispatch('roster-week-changed');
    }

    public function copyToNextWeek(CopyWeekAction $copy, ResolveRosterWeekAction $resolveWeek): void
    {
        $this->authorize('update', PlannedShift::class);
        $payload = $this->payload(app(ListRosterWeekAction::class));
        $workerIds = array_map(fn ($worker) => (int) $worker['id'], $payload['workers']);
        [$monday] = $resolveWeek->handle($this->weekStart);
        $target = $monday->copy()->addWeek()->toDateString();

        Validator::make(
            [
                'source_week_start' => $this->weekStart,
                'target_week_start' => $target,
                'worker_ids' => $workerIds,
            ],
            CopyWeekRequest::rulesFor(),
        )->validate();

        try {
            $copy->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new CopyWeekData($this->weekStart, $target, $workerIds),
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            session()->flash('time_flash_error', __($e->getMessage()));

            return;
        }

        $this->weekStart = $target;
        session()->flash('time_flash', __('time.schedule.copied'));
        $this->dispatch('roster-week-changed');
    }

    public function publish(PublishWeekAction $publish): void
    {
        $this->authorize('publish', PlannedShift::class);
        $payload = $this->payload(app(ListRosterWeekAction::class));
        $workerIds = array_map(fn ($worker) => (int) $worker['id'], $payload['workers']);

        Validator::make(
            [
                'week_start' => $this->weekStart,
                'worker_ids' => $workerIds,
            ],
            PublishWeekRequest::rulesFor(),
        )->validate();

        try {
            $publish->handle(
                Tenant::query()->findOrFail(Tenancy::id()),
                new PublishWeekData($this->weekStart, $workerIds),
                auth()->id(),
            );
        } catch (RosterValidationException|InvalidArgumentException $e) {
            session()->flash('time_flash_error', __($e->getMessage()));

            return;
        }

        session()->flash('time_flash', __('time.schedule.published'));
        $this->dispatch('roster-week-changed');
    }

    public function openCreateType(): void
    {
        $this->authorize('create', ShiftType::class);
        $this->resetTypeForm();
        $this->showTypeModal = true;
    }

    public function openEditType(int $typeId): void
    {
        $type = ShiftType::query()->findOrFail($typeId);
        $this->authorize('update', $type);
        $this->editingTypeId = $type->id;
        $this->typeCode = $type->code;
        $this->typeLabel = $type->label;
        $this->typeStart = $type->start_time;
        $this->typeEnd = $type->end_time;
        $this->typeBreak = (int) $type->break_minutes;
        $this->typeColor = $type->color->value;
        $this->showTypeModal = true;
    }

    public function closeTypeModal(): void
    {
        $this->showTypeModal = false;
        $this->resetTypeForm();
    }

    public function saveType(SaveShiftTypeAction $save): void
    {
        $rules = SaveShiftTypeRequest::rulesFor();
        $validated = $this->validate([
            'typeCode' => $rules['code'],
            'typeLabel' => $rules['label'],
            'typeStart' => $rules['start_time'],
            'typeEnd' => $rules['end_time'],
            'typeBreak' => $rules['break_minutes'],
            'typeColor' => $rules['color'],
        ], [], [
            'typeCode' => __('time.schedule.types.code'),
            'typeLabel' => __('time.schedule.types.label'),
            'typeStart' => __('time.schedule.types.start'),
            'typeEnd' => __('time.schedule.types.end'),
            'typeBreak' => __('time.schedule.types.break'),
            'typeColor' => __('time.schedule.types.color'),
        ]);

        $color = ShiftTypeColor::from($validated['typeColor']);

        try {
            if ($this->editingTypeId) {
                $type = ShiftType::query()->findOrFail($this->editingTypeId);
                $this->authorize('update', $type);
                $saved = $save->handle(
                    Tenant::query()->findOrFail(Tenancy::id()),
                    new SaveShiftTypeData(
                        code: $validated['typeCode'],
                        label: $validated['typeLabel'],
                        startTime: $validated['typeStart'],
                        endTime: $validated['typeEnd'],
                        breakMinutes: (int) $validated['typeBreak'],
                        color: $color,
                        isActive: $type->is_active,
                    ),
                    auth()->id(),
                    $type->id,
                );
            } else {
                $this->authorize('create', ShiftType::class);
                $saved = $save->handle(
                    Tenant::query()->findOrFail(Tenancy::id()),
                    new SaveShiftTypeData(
                        code: $validated['typeCode'],
                        label: $validated['typeLabel'],
                        startTime: $validated['typeStart'],
                        endTime: $validated['typeEnd'],
                        breakMinutes: (int) $validated['typeBreak'],
                        color: $color,
                        isActive: true,
                    ),
                    auth()->id(),
                );
            }
        } catch (RosterValidationException|InvalidArgumentException $e) {
            $this->addError('typeCode', __($e->getMessage()));

            return;
        }

        $this->selectedTypeId = $saved->id;
        $this->closeTypeModal();
        session()->flash('time_flash', __('time.schedule.types.saved'));
        $this->dispatch('roster-week-changed');
    }

    public function editSelectedType(): void
    {
        if ($this->selectedTypeId === null) {
            return;
        }

        $this->openEditType($this->selectedTypeId);
    }

    public function toggleSelectedTypeActive(SetShiftTypeActiveAction $setActive): void
    {
        if ($this->selectedTypeId === null) {
            return;
        }

        $this->toggleTypeActive($this->selectedTypeId, $setActive);
    }

    public function toggleTypeActive(int $typeId, SetShiftTypeActiveAction $setActive): void
    {
        $type = ShiftType::query()->findOrFail($typeId);
        $this->authorize('update', $type);
        $setActive->handle($type, ! $type->is_active, auth()->id());
        $this->dispatch('roster-week-changed');
    }

    public function render(ListShiftTypesAction $listTypes, ListRosterWeekAction $listWeek)
    {
        $types = $listTypes->handle((int) Tenancy::id(), false);
        $snapshot = $listWeek->handle(
            (int) Tenancy::id(),
            $this->weekStart,
            $this->teamFilter,
            auth()->user(),
        );

        $selectedType = collect($types)->first(
            fn (ShiftType $type): bool => $type->id === $this->selectedTypeId,
        );

        return view('livewire.time.roster-index', [
            'shiftTypes' => $types,
            'selectedType' => $selectedType,
            'colors' => ShiftTypeColor::cases(),
            'teams' => $snapshot->teams,
            'weekLabel' => $snapshot->weekStart.' – '.$snapshot->weekEnd,
            'snapshot' => $snapshot,
            'alarmCount' => $this->timeNavAlarmCount(),
        ]);
    }

    private function resetTypeForm(): void
    {
        $this->editingTypeId = null;
        $this->typeCode = '';
        $this->typeLabel = '';
        $this->typeStart = '07:00';
        $this->typeEnd = '15:00';
        $this->typeBreak = 0;
        $this->typeColor = ShiftTypeColor::default()->value;
    }
}
