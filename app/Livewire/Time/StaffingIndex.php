<?php

namespace App\Livewire\Time;

use App\Actions\Locations\UpdateLocationAction;
use App\Enums\ShiftTypeKind;
use App\Livewire\Concerns\ProvidesTimeNavAlarmCount;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Tenancy;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Minimum bezetting per locatie: dagelijkse aantallen per diensttype uit de
 * legende (locations.min_staffing: {shift_type_id: min}). Puur indicatie in
 * het uurrooster — blokkeert nooit opslaan of publiceren.
 *
 * Verouderde regels (type inactief/verwijderd) blijven vergrijsd zichtbaar en
 * worden nooit ongemerkt gewist: enkel expliciete rij-verwijdering schrapt ze.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class StaffingIndex extends Component
{
    use AuthorizesRequests;
    use ProvidesTimeNavAlarmCount;

    /** @var array<int, list<array{type_id: string, min: string, stale_label: ?string}>> */
    public array $staffingRows = [];

    public function mount(): void
    {
        $this->authorize('viewAny', PlannedShift::class);
        TimeModuleAccess::assertEnabledForTenantId((int) Tenancy::id());

        $tenant = Tenant::query()->find((int) Tenancy::id());
        abort_if(CheckmateMode::isActive($tenant), 404);

        $this->hydrateAll();
    }

    public function addStaffingRow(int $locationId): void
    {
        $this->staffingRows[$locationId][] = ['type_id' => '', 'min' => '1', 'stale_label' => null];
    }

    public function removeStaffingRow(int $locationId, int $index): void
    {
        unset($this->staffingRows[$locationId][$index]);
        $this->staffingRows[$locationId] = array_values($this->staffingRows[$locationId] ?? []);
    }

    public function saveLocation(int $locationId, UpdateLocationAction $updateLocation): void
    {
        $location = $this->scopedLocations()->firstWhere('id', $locationId);
        abort_if($location === null, 404);
        $this->authorize('update', $location);

        if (! $this->typeIdsValid($location)) {
            $this->addError('staffingRows', __('time.staffing.errors.type_invalid'));

            return;
        }

        $updateLocation->handle(
            $location,
            ['min_staffing' => $this->normalizedRows($locationId)],
            (int) auth()->id(),
        );
        $this->staffingRows[$locationId] = $this->hydrateRows($location->fresh());
        session()->flash('time_flash', __('time.staffing.saved', ['location' => (string) $location->name]));
    }

    public function render()
    {
        return view('livewire.time.staffing-index', [
            'staffingLocations' => $this->scopedLocations(),
            'staffingTypeOptions' => $this->typeOptions(),
            'alarmCount' => $this->timeNavAlarmCount(),
        ]);
    }

    /**
     * @return Collection<int, Location>
     */
    private function scopedLocations(): Collection
    {
        $allowed = auth()->user()?->accessibleLocationIds();

        return Location::query()
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))
            ->orderBy('name')
            ->get(['id', 'name', 'tenant_id', 'min_staffing']);
    }

    private function hydrateAll(): void
    {
        $this->staffingRows = [];
        foreach ($this->scopedLocations() as $location) {
            $this->staffingRows[$location->id] = $this->hydrateRows($location);
        }
    }

    /**
     * @return list<array{type_id: string, min: string, stale_label: ?string}>
     */
    private function hydrateRows(Location $location): array
    {
        $stored = (array) ($location->min_staffing ?? []);
        $storedIds = array_map('intval', array_keys($stored));

        // Alle types (ook inactief/afwezig-kind) zodat verouderde regels
        // herkenbaar getoond kunnen worden i.p.v. ongemerkt weg te vallen.
        $known = ShiftType::query()
            ->where('tenant_id', (int) $location->tenant_id)
            ->whereIn('id', $storedIds)
            ->get(['id', 'code', 'is_active', 'kind'])
            ->keyBy('id');

        $rows = [];
        foreach ($stored as $typeId => $min) {
            $typeId = (int) $typeId;
            $type = $known->get($typeId);
            $usable = $type !== null && $type->is_active && $type->kind?->isWork();

            $rows[] = [
                'type_id' => (string) $typeId,
                'min' => (string) (int) $min,
                'stale_label' => $usable ? null : ($type?->code ?? '?'),
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, int>
     */
    private function normalizedRows(int $locationId): array
    {
        $map = [];
        foreach ($this->staffingRows[$locationId] ?? [] as $row) {
            $typeId = (int) ($row['type_id'] ?? 0);
            $min = (int) ($row['min'] ?? 0);
            if ($typeId > 0 && $min > 0 && ! array_key_exists($typeId, $map)) {
                $map[$typeId] = min($min, 99);
            }
        }

        return $map;
    }

    /**
     * Ids die opgeslagen mogen worden: actieve werk-types ∪ ids die al in de
     * opgeslagen config staan (verouderde regels blijven bewaard tot verwijderd).
     */
    private function typeIdsValid(Location $location): bool
    {
        $requested = array_keys($this->normalizedRows((int) $location->id));
        if ($requested === []) {
            return true;
        }

        $allowed = ShiftType::query()
            ->where('tenant_id', (int) $location->tenant_id)
            ->where('is_active', true)
            ->where('kind', ShiftTypeKind::Work->value)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->merge(array_map('intval', array_keys((array) ($location->min_staffing ?? []))))
            ->all();

        return count(array_diff($requested, $allowed)) === 0;
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function typeOptions(): array
    {
        return ShiftType::query()
            ->where('tenant_id', (int) Tenancy::id())
            ->where('is_active', true)
            ->where('kind', ShiftTypeKind::Work->value)
            ->orderBy('code')
            ->get(['id', 'code', 'label'])
            ->map(fn (ShiftType $type): array => [
                'id' => (int) $type->id,
                'label' => trim($type->code.' — '.$type->label, ' —'),
            ])
            ->all();
    }
}
