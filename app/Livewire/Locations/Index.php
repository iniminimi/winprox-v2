<?php

namespace App\Livewire\Locations;

use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Actions\Locations\UpdateLocationAction;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Models\Location;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    public bool $showInactive = false;

    public bool $showModal = false;

    public ?int $editingLocationId = null;

    public string $name = '';

    public string $street = '';

    public string $house_number = '';

    public string $postal_code = '';

    public string $city = '';

    public string $country_code = 'BE';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Location::class);
    }

    public function updatedSearch(): void
    {
        // Re-render list.
    }

    public function openCreate(): void
    {
        $this->authorize('create', Location::class);
        $this->resetForm();
        $this->editingLocationId = null;
        $this->showModal = true;
    }

    public function openEdit(int $locationId): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('update', $location);

        $this->editingLocationId = $location->id;
        $this->name = (string) $location->name;
        $this->street = (string) ($location->street ?? '');
        $this->house_number = (string) ($location->house_number ?? '');
        $this->postal_code = (string) ($location->postal_code ?? '');
        $this->city = (string) ($location->city ?? '');
        $this->country_code = (string) ($location->country_code ?? 'BE');
        $this->notes = (string) ($location->notes ?? '');
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function save(
        CreateLocationAction $createLocation,
        UpdateLocationAction $updateLocation,
    ): void {
        $payload = [
            'name' => $this->name,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'country_code' => $this->country_code,
            'notes' => $this->notes,
        ];

        if ($this->editingLocationId === null) {
            $this->authorize('create', Location::class);
            $validated = $this->validate(StoreLocationRequest::ruleSet());
            $createLocation->handle($validated, (int) auth()->user()->tenant_id, (int) auth()->id());
            session()->flash('success', __('locations.flash.created'));
        } else {
            $location = Location::findOrFail($this->editingLocationId);
            $this->authorize('update', $location);
            $validated = $this->validate(UpdateLocationRequest::ruleSet());
            $updateLocation->handle($location, $validated, (int) auth()->id());
            session()->flash('success', __('locations.flash.updated'));
        }

        $this->closeModal();
    }

    public function deactivate(int $locationId, DeactivateLocationAction $deactivateLocation): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('deactivate', $location);
        $deactivateLocation->handle($location);
        session()->flash('success', __('locations.flash.deactivated'));
    }

    private function resetForm(): void
    {
        $this->reset([
            'name', 'street', 'house_number', 'postal_code', 'city', 'notes',
        ]);
        $this->country_code = 'BE';
        $this->resetErrorBag();
    }

    public function render()
    {
        $term = trim($this->search);

        $locations = Location::query()
            ->withCount('units')
            ->when(! $this->showInactive, fn ($q) => $q->where('is_active', true))
            ->when($term !== '', function ($q) use ($term) {
                $like = '%'.$term.'%';
                $q->where(function ($query) use ($like) {
                    $query->where('name', 'like', $like)
                        ->orWhere('street', 'like', $like)
                        ->orWhere('postal_code', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('address', 'like', $like);
                });
            })
            ->orderBy('name')
            ->get();

        return view('livewire.locations.index', [
            'locations' => $locations,
        ]);
    }
}
