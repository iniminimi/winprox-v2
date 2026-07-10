<?php

namespace App\Livewire\Time;

use App\Actions\Time\CreateClockPointAction;
use App\Actions\Time\SetClockPointActiveAction;
use App\Actions\Time\UpdateClockPointAction;
use App\Http\Requests\Time\StoreClockPointRequest;
use App\Http\Requests\Time\UpdateClockPointRequest;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class ClockPointsIndex extends Component
{
    use AuthorizesRequests;

    public bool $showModal = false;
    public ?int $editingClockPointId = null;
    public string $name = '';
    public ?int $locationId = null;
    public int $sortOrder = 0;

    public function mount(): void
    {
        $this->authorize('viewAny', ClockPoint::class);
    }

    public function openCreate(): void
    {
        $this->authorize('create', ClockPoint::class);
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEdit(int $clockPointId): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('update', $clockPoint);

        $this->editingClockPointId = $clockPoint->id;
        $this->name = $clockPoint->name;
        $this->locationId = $clockPoint->location_id;
        $this->sortOrder = (int) $clockPoint->sort_order;
        $this->showModal = true;
    }

    public function save(CreateClockPointAction $create, UpdateClockPointAction $update): void
    {
        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $rules = $this->editingClockPointId
            ? UpdateClockPointRequest::rulesFor()
            : StoreClockPointRequest::rulesFor();

        $validated = $this->validate([
            'name' => $rules['name'],
            'locationId' => $rules['location_id'],
            'sortOrder' => $rules['sort_order'] ?? ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], [
            'name' => __('time.clock_points.fields.name'),
            'locationId' => __('time.clock_points.fields.location'),
            'sortOrder' => __('time.clock_points.fields.sort_order'),
        ]);

        $payload = [
            'name' => $validated['name'],
            'location_id' => $validated['locationId'] ?: null,
            'sort_order' => (int) ($validated['sortOrder'] ?? 0),
        ];

        if ($this->editingClockPointId) {
            $clockPoint = ClockPoint::query()->findOrFail($this->editingClockPointId);
            $this->authorize('update', $clockPoint);
            $update->handle($clockPoint, $payload, auth()->id());
        } else {
            $this->authorize('create', ClockPoint::class);
            $create->handle($tenant, $payload + ['is_active' => true], auth()->id());
        }

        $this->showModal = false;
        $this->resetForm();
        session()->flash('time_flash', __('time.clock_points.saved'));
    }

    public function toggleActive(int $clockPointId, SetClockPointActiveAction $setActive): void
    {
        $clockPoint = ClockPoint::query()->findOrFail($clockPointId);
        $this->authorize('update', $clockPoint);
        $setActive->handle($clockPoint, ! $clockPoint->is_active, auth()->id());
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.time.clock-points-index', [
            'clockPoints' => ClockPoint::query()->with('location')->orderBy('sort_order')->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function resetForm(): void
    {
        $this->editingClockPointId = null;
        $this->name = '';
        $this->locationId = null;
        $this->sortOrder = 0;
        $this->resetErrorBag();
    }
}
