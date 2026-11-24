<?php

declare(strict_types=1);

namespace App\Livewire\Sense;

use App\Actions\Sense\CreateSensePointAction;
use App\Actions\Sense\DeleteSensePointAction;
use App\Actions\Sense\ListSenseRoomsAction;
use App\Actions\Sense\ListSenseUnitChoicesAction;
use App\Actions\Sense\UpdateSenseBandsAction;
use App\Models\SensePoint;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Sense')]
class Index extends Component
{
    use AuthorizesRequests;

    public bool $showCreate = false;

    public bool $showDelete = false;

    public ?int $unitId = null;

    public ?int $deleteId = null;

    public ?string $bandError = null;

    public function mount(): void
    {
        $this->authorize('viewAny', SensePoint::class);
    }

    public function openCreate(): void
    {
        $this->authorize('create', SensePoint::class);
        $this->unitId = null;
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
        $this->unitId = null;
    }

    public function createPoint(CreateSensePointAction $create): void
    {
        $this->authorize('create', SensePoint::class);
        $this->validate([
            'unitId' => ['required', 'integer'],
        ]);

        try {
            $create->handle((int) $this->unitId, (int) Tenancy::id(), (int) auth()->id());
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            $this->addError('unitId', is_string($message) ? $message : __('sense.errors.unit_invalid'));

            return;
        }

        $this->closeCreate();
    }

    public function saveBands(int $pointId, string $metric, float $low, ?float $target, float $high, UpdateSenseBandsAction $update): bool
    {
        $point = SensePoint::query()->findOrFail($pointId);
        $this->authorize('update', $point);
        $this->bandError = null;

        try {
            $update->handle($point, [
                'metric' => $metric,
                'low' => $low,
                'target' => $target,
                'high' => $high,
            ], (int) Tenancy::id(), (int) auth()->id());
        } catch (ValidationException $exception) {
            $this->bandError = collect($exception->errors())->flatten()->first();

            return false;
        }

        return true;
    }

    public function askDelete(int $pointId): void
    {
        $point = SensePoint::query()->findOrFail($pointId);
        $this->authorize('delete', $point);
        $this->deleteId = $pointId;
        $this->showDelete = true;
    }

    public function closeDelete(): void
    {
        $this->showDelete = false;
        $this->deleteId = null;
    }

    public function deletePoint(DeleteSensePointAction $delete): void
    {
        if ($this->deleteId === null) {
            return;
        }

        $point = SensePoint::query()->findOrFail($this->deleteId);
        $this->authorize('delete', $point);
        $delete->handle($point, (int) Tenancy::id(), (int) auth()->id());
        $this->closeDelete();
    }

    public function render(ListSenseRoomsAction $list, ListSenseUnitChoicesAction $choices)
    {
        $tenantId = (int) Tenancy::id();

        $user = auth()->user();

        return view('livewire.sense.index', [
            'rooms' => $list->handle($tenantId),
            'unitChoices' => $this->showCreate ? $choices->handle($tenantId) : [],
            'canManage' => $user !== null && $user->can('create', SensePoint::class),
        ]);
    }
}
