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

    public string $locationFormName = '';

    public string $locationFormStreet = '';

    public string $locationFormHouseNumber = '';

    public string $locationFormPostalCode = '';

    public string $locationFormCity = '';

    public string $locationFormCountryCode = 'BE';

    public string $locationFormNotes = '';

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
        $this->locationFormName = (string) $location->name;
        $this->locationFormStreet = (string) ($location->street ?? $location->address ?? '');
        $this->locationFormHouseNumber = (string) ($location->house_number ?? '');
        $this->locationFormPostalCode = (string) ($location->postal_code ?? '');
        $this->locationFormCity = (string) ($location->city ?? '');
        $this->locationFormCountryCode = (string) ($location->country_code ?? 'BE');
        $this->locationFormNotes = (string) ($location->notes ?? '');
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
        $payload = $this->locationFormPayload();

        if ($this->editingLocationId === null) {
            $this->authorize('create', Location::class);
            $validated = StoreLocationRequest::validatePayload($payload);
            $createLocation->handle($validated, (int) auth()->user()->tenant_id, (int) auth()->id());
            session()->flash('success', __('locations.flash.created'));
        } else {
            $location = Location::findOrFail($this->editingLocationId);
            $this->authorize('update', $location);
            $validated = UpdateLocationRequest::validatePayload($payload);
            $updateLocation->handle($location, $validated, (int) auth()->id());
            session()->flash('success', __('locations.flash.updated'));
        }

        $this->closeModal();
    }

    public function deactivate(int $locationId, DeactivateLocationAction $deactivateLocation): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('deactivate', $location);
        $deactivateLocation->handle($location, (int) auth()->id());
        session()->flash('success', __('locations.flash.deactivated'));
    }

    /**
     * @return array<string, string>
     */
    private function locationFormPayload(): array
    {
        return [
            'name' => $this->locationFormName,
            'street' => $this->locationFormStreet,
            'house_number' => $this->locationFormHouseNumber,
            'postal_code' => $this->locationFormPostalCode,
            'city' => $this->locationFormCity,
            'country_code' => $this->locationFormCountryCode,
            'notes' => $this->locationFormNotes,
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'locationFormName', 'locationFormStreet', 'locationFormHouseNumber', 'locationFormPostalCode', 'locationFormCity', 'locationFormNotes', 'editingLocationId',
        ]);
        $this->editingLocationId = null;
        $this->locationFormCountryCode = 'BE';
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
                        ->orWhere('house_number', 'like', $like)
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
