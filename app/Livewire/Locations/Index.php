<?php

namespace App\Livewire\Locations;

use App\Actions\Locations\ActivateLocationAction;
use App\Actions\Locations\CreateLocationAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Actions\Locations\UpdateLocationAction;
use App\Actions\Units\ImportUnitsAction;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Http\Requests\Units\ImportUnitsRequest;
use App\Models\Location;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Index extends Component
{
    use WithFileUploads;
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

    public $importFile = null;

    public array $importErrors = [];

    public ?int $importedCount = null;

    public bool $showImportModal = false;

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

    public function activate(int $locationId, ActivateLocationAction $activateLocation): void
    {
        $location = Location::findOrFail($locationId);
        $this->authorize('update', $location);
        $activateLocation->handle($location, (int) auth()->id());
        session()->flash('success', __('locations.flash.activated'));
    }

    public function openImportModal(): void
    {
        $this->authorize('create', Location::class);
        $this->importFile = null;
        $this->importErrors = [];
        $this->importedCount = null;
        $this->showImportModal = true;
    }

    public function closeImportModal(): void
    {
        $this->showImportModal = false;
        $this->importFile = null;
        $this->importErrors = [];
        $this->importedCount = null;
    }

    public function importUnits(ImportUnitsAction $importUnits): void
    {
        $this->authorize('create', Location::class);

        if ($this->importFile === null) {
            $this->importErrors = ['Er moet een bestand worden geüpload.'];
            return;
        }

        // Manual validation since Form Request::validate() doesn't work in Livewire
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['file' => $this->importFile],
            [
                'file' => 'required|file|mimes:csv,txt|max:10240',
            ],
            [
                'file.required' => 'Er moet een bestand worden geüpload.',
                'file.mimes' => 'Het bestand moet een CSV-bestand zijn.',
                'file.max' => 'Het bestand mag maximaal 10MB groot zijn.',
            ]
        );

        if ($validator->fails()) {
            $this->importErrors = $validator->errors()->all();
            return;
        }

        $result = $importUnits->handle($this->importFile, (int) auth()->id());

        if ($result['success']) {
            $this->importedCount = $result['count'];
            $this->importErrors = [];
            session()->flash('success', __('locations.flash.imported', ['count' => $result['count']]));
            $this->closeImportModal();
        } else {
            $this->importErrors = $result['errors'];
        }
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

        $hasInactiveLocations = Location::query()->where('is_active', false)->exists();

        return view('livewire.locations.index', [
            'locations' => $locations,
            'hasInactiveLocations' => $hasInactiveLocations,
        ]);
    }
}
