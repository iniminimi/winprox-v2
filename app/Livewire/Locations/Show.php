<?php

namespace App\Livewire\Locations;

use App\Actions\Locations\BulkCreateUnitsAction;
use App\Actions\Locations\CreateUnitAction;
use App\Actions\Locations\DeactivateLocationAction;
use App\Actions\Locations\DeactivateUnitAction;
use App\Actions\Locations\DeleteUnitAction;
use App\Actions\Locations\DeleteUnitBulkBatchAction;
use App\Actions\Locations\UpdateLocationAction;
use App\Actions\Locations\UpdateUnitAction;
use App\Http\Requests\Locations\BulkCreateUnitsRequest;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\StoreUnitRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Http\Requests\Locations\UpdateUnitRequest;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Unit;
use App\Models\UnitBulkBatch;
use App\Support\Units\UnitBulkBatchRegistry;
use App\Support\Units\UnitBulkNaming;
use App\Support\Units\UnitDeletionGuard;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Show extends Component
{
    public Location $location;

    public bool $showLocationModal = false;

    public bool $showUnitModal = false;

    public bool $showBulkModal = false;

    public ?int $editingUnitId = null;

    public string $name = '';

    public string $street = '';

    public string $house_number = '';

    public string $postal_code = '';

    public string $city = '';

    public string $country_code = 'BE';

    public string $notes = '';

    public string $unitName = '';

    public ?int $unitTeamId = null;

    public string $bulkFloors = '1';

    public string $bulkRoomsPerFloor = '1';

    public string $bulkScheme = UnitBulkNaming::SCHEME_COMPACT_2;

    public string $bulkPrefix = '';

    public ?int $bulkTeamId = null;

    public function mount(Location $location): void
    {
        $this->authorize('view', $location);
        $this->location = $location;
    }

    public function openEditLocation(): void
    {
        $this->authorize('update', $this->location);
        $this->name = (string) $this->location->name;
        $this->street = (string) ($this->location->street ?? '');
        $this->house_number = (string) ($this->location->house_number ?? '');
        $this->postal_code = (string) ($this->location->postal_code ?? '');
        $this->city = (string) ($this->location->city ?? '');
        $this->country_code = (string) ($this->location->country_code ?? 'BE');
        $this->notes = (string) ($this->location->notes ?? '');
        $this->showLocationModal = true;
    }

    public function saveLocation(UpdateLocationAction $updateLocation): void
    {
        $this->authorize('update', $this->location);
        $validated = UpdateLocationRequest::validatePayload([
            'name' => $this->name,
            'street' => $this->street,
            'house_number' => $this->house_number,
            'postal_code' => $this->postal_code,
            'city' => $this->city,
            'country_code' => $this->country_code,
            'notes' => $this->notes,
        ]);
        $this->location = $updateLocation->handle($this->location, $validated, (int) auth()->id());
        $this->showLocationModal = false;
        session()->flash('success', __('locations.flash.updated'));
    }

    public function deactivateLocation(DeactivateLocationAction $deactivateLocation): void
    {
        $this->authorize('deactivate', $this->location);
        $deactivateLocation->handle($this->location, (int) auth()->id());
        session()->flash('success', __('locations.flash.deactivated'));

        $this->redirect(route('locations.index'), navigate: true);
    }

    public function openCreateUnit(): void
    {
        $this->authorize('create', Unit::class);
        $this->editingUnitId = null;
        $this->unitName = '';
        $this->unitTeamId = null;
        $this->resetErrorBag();
        $this->showUnitModal = true;
    }

    public function openEditUnit(int $unitId): void
    {
        $unit = Unit::where('location_id', $this->location->id)->findOrFail($unitId);
        $this->authorize('update', $unit);
        $this->editingUnitId = $unit->id;
        $this->unitName = $unit->name;
        $this->unitTeamId = $unit->default_internal_team_id;
        $this->resetErrorBag();
        $this->showUnitModal = true;
    }

    public function saveUnit(CreateUnitAction $createUnit, UpdateUnitAction $updateUnit): void
    {
        $rules = $this->editingUnitId === null
            ? StoreUnitRequest::ruleSet($this->location->id)
            : UpdateUnitRequest::ruleSetFor($this->location->id, $this->editingUnitId);

        $validated = $this->validate([
            'unitName' => $rules['name'],
            'unitTeamId' => $rules['default_internal_team_id'],
        ], [
            'unitName.required' => __('locations.units.errors.name_required'),
            'unitName.unique' => __('locations.units.errors.duplicate_name'),
        ]);

        $payload = [
            'name' => $validated['unitName'],
            'default_internal_team_id' => $validated['unitTeamId'] ?? null,
        ];

        if ($this->editingUnitId === null) {
            $this->authorize('create', Unit::class);
            try {
                $createUnit->handle($this->location, $payload, (int) auth()->user()->tenant_id, (int) auth()->id());
            } catch (InvalidArgumentException $e) {
                if ($e->getMessage() === 'unit_limit_exceeded') {
                    $this->addError('unitName', __('locations.errors.unit_limit'));

                    return;
                }

                throw $e;
            }
            session()->flash('success', __('locations.units.flash.created'));
        } else {
            $unit = Unit::findOrFail($this->editingUnitId);
            $this->authorize('update', $unit);
            $updateUnit->handle($unit, $payload, (int) auth()->id());
            session()->flash('success', __('locations.units.flash.updated'));
        }

        $this->showUnitModal = false;
        $this->location->refresh();
    }

    public function deactivateUnit(int $unitId, DeactivateUnitAction $deactivateUnit): void
    {
        $unit = Unit::where('location_id', $this->location->id)->findOrFail($unitId);
        $this->authorize('deactivate', $unit);
        $deactivateUnit->handle($unit, (int) auth()->id());
        session()->flash('success', __('locations.units.flash.deactivated'));
        $this->location->refresh();
    }

    public function deleteUnit(int $unitId, DeleteUnitAction $deleteUnit): void
    {
        $unit = Unit::where('location_id', $this->location->id)->findOrFail($unitId);
        $this->authorize('delete', $unit);

        try {
            $deleteUnit->handle($unit, (int) auth()->id());
            session()->flash('success', __('locations.units.flash.deleted'));
        } catch (InvalidArgumentException $e) {
            session()->flash('error', __(UnitDeletionGuard::blockMessageKey($e->getMessage())));
        }

        $this->location->refresh();
    }

    public function openBulkModal(): void
    {
        $this->authorize('create', Unit::class);
        $this->bulkFloors = '3';
        $this->bulkRoomsPerFloor = '1';
        $this->bulkScheme = UnitBulkNaming::SCHEME_COMPACT_2;
        $this->bulkPrefix = '';
        $this->bulkTeamId = null;
        $this->showBulkModal = true;
    }

    /**
     * @return list<string>
     */
    public function bulkPreviewNames(): array
    {
        if (! $this->showBulkModal) {
            return [];
        }

        $floorCount = max(1, (int) trim($this->bulkFloors));
        $roomsPerFloor = max(1, (int) trim($this->bulkRoomsPerFloor));

        if (UnitBulkNaming::validateConfig($floorCount, $roomsPerFloor, $this->bulkScheme) !== null) {
            return [];
        }

        try {
            $names = UnitBulkNaming::generate($floorCount, $roomsPerFloor, $this->bulkScheme, trim($this->bulkPrefix));
        } catch (\InvalidArgumentException) {
            return [];
        }

        $existing = Unit::query()
            ->where('location_id', $this->location->id)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        return array_values(array_slice(array_diff($names, $existing), 0, 16));
    }

    public function createBulk(BulkCreateUnitsAction $bulkCreate): void
    {
        $this->authorize('create', Unit::class);

        $bulkRules = BulkCreateUnitsRequest::ruleSet();
        $validated = $this->validate([
            'bulkFloors' => $bulkRules['floors'],
            'bulkRoomsPerFloor' => $bulkRules['rooms_per_floor'],
            'bulkScheme' => $bulkRules['scheme'],
            'bulkPrefix' => $bulkRules['prefix'],
            'bulkTeamId' => $bulkRules['default_internal_team_id'],
        ]);

        try {
            $result = $bulkCreate->handle($this->location, [
                'floors' => (int) $validated['bulkFloors'],
                'rooms_per_floor' => (int) $validated['bulkRoomsPerFloor'],
                'scheme' => $validated['bulkScheme'],
                'prefix' => $validated['bulkPrefix'] ?? '',
                'default_internal_team_id' => $validated['bulkTeamId'] ?? null,
            ], (int) auth()->user()->tenant_id, (int) auth()->id());

            session()->flash('success', __('locations.bulk.created', ['count' => $result['created']]));
            $this->showBulkModal = false;
            $this->location->refresh();
        } catch (InvalidArgumentException $e) {
            $key = match ($e->getMessage()) {
                'scheme_rooms' => 'locations.bulk.errors.scheme_rooms',
                'scheme_floors' => 'locations.bulk.errors.scheme_floors',
                'scheme_range' => 'locations.bulk.errors.scheme_range',
                'names_exist' => 'locations.bulk.errors.names_exist',
                'too_many' => 'locations.bulk.errors.too_many',
                'unit_limit_exceeded' => 'locations.errors.unit_limit',
                default => 'locations.bulk.errors.invalid',
            };
            $this->addError('bulkFloors', __($key));
        }
    }

    public function deleteBulkBatch(int $batchId, DeleteUnitBulkBatchAction $deleteBatch): void
    {
        $batch = UnitBulkBatch::where('location_id', $this->location->id)->findOrFail($batchId);
        $this->authorize('create', Unit::class);

        $result = $deleteBatch->handle($batch, (int) auth()->id());

        if ($result['deleted'] === 0) {
            session()->flash('error', __('locations.bulk.batch_nothing_deletable'));
        } else {
            session()->flash('success', __('locations.bulk.batch_deleted', [
                'deleted' => $result['deleted'],
                'skipped' => $result['skipped'],
            ]));
        }

        $this->location->refresh();
    }

    public function render()
    {
        $this->location->loadMissing(['units' => fn ($q) => $q
            ->with('defaultInternalTeam:id,name')
            ->withCount('issues')
            ->orderBy('name'),
        ]);

        $activeLocations = Location::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->pluck('id')
            ->all();

        $currentIndex = array_search($this->location->id, $activeLocations, true);
        $prevId = ($currentIndex !== false && $currentIndex > 0) ? $activeLocations[$currentIndex - 1] : null;
        $nextId = ($currentIndex !== false && $currentIndex < count($activeLocations) - 1)
            ? $activeLocations[$currentIndex + 1]
            : null;

        $bulkSummaries = UnitBulkBatchRegistry::recentBatchesForLocation($this->location)
            ->map(fn (UnitBulkBatch $batch) => array_merge(
                ['batch' => $batch],
                UnitBulkBatchRegistry::summary($batch),
            ));

        $teams = InternalTeam::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.locations.show', [
            'units' => $this->location->units,
            'bulkSummaries' => $bulkSummaries,
            'teams' => $teams,
            'prevLocationId' => $prevId,
            'nextLocationId' => $nextId,
            'bulkPreview' => $this->bulkPreviewNames(),
        ]);
    }
}
