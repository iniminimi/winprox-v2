<?php

namespace App\Actions\Time;

use App\Enums\ShiftTypeKind;
use App\Models\Location;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Berekent tekorten t.o.v. de geconfigureerde dagelijkse minimumbezetting
 * per locatie (locations.min_staffing: { shift_type_id: min_count }).
 *
 * Regels (afgesproken):
 * - Telling is tenant-breed per locatie; weergave beperkt tot actor-locaties
 *   en het actieve locatiefilter.
 * - Enkel zichtbare dagen >= vandaag (weekenden enkel als getoond).
 * - Enkel unieke uitvoerders tellen (split-shifts = 1), enkel werk-kind,
 *   draft + published.
 * - Effectieve locatie: planned_shifts.location_id, anders de locatie van
 *   de geplande eenheid (unit_id), anders de vaste groep van de uitvoerder
 *   (default_unit), anders exact één toegewezen locatie; anders telt de
 *   dienst nergens (bekende v1-beperking zonder enige locatie-aanknoping).
 * - Verouderde config (type inactief/verwijderd) wordt overgeslagen.
 * - Indicatie, nooit blokkerend. Limiet 5 vóór groepering per locatie.
 */
final class ListLocationStaffingShortfallsAction
{
    public const LIMIT = 5;

    /**
     * @param  list<string>  $visibleDates
     * @param  list<int>|null  $actorLocationIds  null = alle locaties van de tenant
     * @return array{total: int, locations: list<array{name: string, items: list<array{date: string, code: string, actual: int, required: int}>}>}
     */
    public function handle(
        int $tenantId,
        array $visibleDates,
        ?array $actorLocationIds = null,
        ?int $activeLocationId = null,
    ): array {
        $empty = ['total' => 0, 'locations' => []];

        $dates = array_values(array_unique(array_filter(
            $visibleDates,
            fn (string $date) => $date >= Carbon::today()->toDateString(),
        )));
        sort($dates);

        if ($dates === []) {
            return $empty;
        }

        // Expliciete tenant is leidend; de ambient tenant-scope mag deze
        // telling niet beïnvloeden (actor-context ≠ getelde tenant).
        $locations = Location::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('min_staffing')
            ->when($actorLocationIds !== null, fn ($q) => $q->whereIn('id', $actorLocationIds))
            ->when($activeLocationId !== null, fn ($q) => $q->whereKey($activeLocationId))
            ->orderBy('name')
            ->get(['id', 'name', 'min_staffing'])
            ->filter(fn (Location $location) => is_array($location->min_staffing) && $location->min_staffing !== [])
            ->values();

        if ($locations->isEmpty()) {
            return $empty;
        }

        $requiredTypeIds = $locations
            ->flatMap(fn (Location $location) => array_keys((array) $location->min_staffing))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // Verouderde config: inactieve/verwijderde types tellen niet mee;
        // enkel werk-types zijn zinvol als bezettingsvereiste.
        $activeTypes = ShiftType::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('kind', ShiftTypeKind::Work->value)
            ->whereIn('id', $requiredTypeIds)
            ->get(['id', 'code'])
            ->keyBy('id');

        // Per locatie: [type_id => min], enkel actieve types.
        $requirements = $locations->mapWithKeys(function (Location $location) use ($activeTypes) {
            $reqs = [];
            foreach ((array) $location->min_staffing as $typeId => $min) {
                $typeId = (int) $typeId;
                $min = (int) $min;
                if ($min > 0 && $activeTypes->has($typeId)) {
                    $reqs[$typeId] = $min;
                }
            }

            return [$location->id => $reqs];
        })->filter(fn (array $reqs) => $reqs !== []);

        if ($requirements->isEmpty()) {
            return $empty;
        }

        $counts = $this->countUniqueWorkers($tenantId, $dates, $activeTypes->keys()->all());

        $shortfalls = [];
        $names = $locations->keyBy('id');

        foreach ($dates as $date) {
            foreach ($requirements as $locationId => $reqs) {
                foreach ($reqs as $typeId => $required) {
                    $actual = $counts["{$date}|{$locationId}|{$typeId}"] ?? 0;
                    if ($actual < $required) {
                        $shortfalls[] = [
                            'date' => $date,
                            'location' => (string) $names[$locationId]->name,
                            'code' => (string) $activeTypes[$typeId]->code,
                            'actual' => $actual,
                            'required' => $required,
                        ];
                    }
                }
            }
        }

        usort($shortfalls, fn (array $a, array $b) => [$a['date'], $a['location'], $a['code']] <=> [$b['date'], $b['location'], $b['code']]);

        $total = count($shortfalls);
        $shown = array_slice($shortfalls, 0, self::LIMIT);

        $groups = [];
        foreach ($shown as $item) {
            $name = $item['location'];
            $groups[$name]['name'] = $name;
            $groups[$name]['items'][] = [
                'date' => $item['date'],
                'code' => $item['code'],
                'actual' => $item['actual'],
                'required' => $item['required'],
            ];
        }

        // Volledige map per datum (geen limiet) voor de dagmarkering in het
        // rooster: elke dag met een tekort kleurt rood en toont al zijn items.
        $byDate = [];
        foreach ($shortfalls as $item) {
            $byDate[$item['date']][] = [
                'location' => $item['location'],
                'code' => $item['code'],
                'actual' => $item['actual'],
                'required' => $item['required'],
            ];
        }

        return ['total' => $total, 'locations' => array_values($groups), 'by_date' => $byDate];
    }

    /**
     * Unieke uitvoerders per (datum, locatie, diensttype).
     *
     * @param  list<string>  $dates
     * @param  list<int>  $typeIds
     * @return array<string, int>  key: "date|location_id|type_id"
     */
    private function countUniqueWorkers(int $tenantId, array $dates, array $typeIds): array
    {
        $shifts = PlannedShift::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('kind', ShiftTypeKind::Work->value)
            ->where(function ($query) use ($dates) {
                foreach ($dates as $date) {
                    $query->orWhereDate('work_date', $date);
                }
            })
            ->whereIn('shift_type_id', $typeIds)
            ->get(['worker_id', 'work_date', 'shift_type_id', 'location_id', 'unit_id']);

        if ($shifts->isEmpty()) {
            return [];
        }

        $workerIds = $shifts->pluck('worker_id')->unique();

        // worker_id => default_unit_id (vaste groep van de uitvoerder).
        $workerUnits = DB::table('workers')
            ->whereIn('id', $workerIds)
            ->whereNotNull('default_unit_id')
            ->pluck('default_unit_id', 'id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // unit_id => location_id (geplande én vaste groepen).
        $unitLocations = DB::table('units')
            ->whereIn('id', array_unique(array_merge(
                $shifts->pluck('unit_id')->filter()->all(),
                array_values($workerUnits),
            )) ?: [0])
            ->whereNotNull('location_id')
            ->pluck('location_id', 'id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // worker_id => [location_ids]; locatie-fallback enkel bij exact één.
        $workerLocations = DB::table('location_worker')
            ->whereIn('worker_id', $workerIds)
            ->get(['worker_id', 'location_id'])
            ->groupBy('worker_id')
            ->map(fn ($rows) => $rows->pluck('location_id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $seen = [];
        foreach ($shifts as $shift) {
            $locationId = $this->resolveLocationId($shift, $unitLocations, $workerUnits, $workerLocations);

            if ($locationId !== null) {
                $seen[$shift->work_date->toDateString().'|'.$locationId.'|'.(int) $shift->shift_type_id.'|'.(int) $shift->worker_id] = true;
            }
        }

        $counts = [];
        foreach (array_keys($seen) as $key) {
            [$date, $locationId, $typeId] = explode('|', $key);
            $counts["{$date}|{$locationId}|{$typeId}"] = ($counts["{$date}|{$locationId}|{$typeId}"] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Effectieve locatie van een dienst: expliciete location_id → geplande
     * eenheid → vaste groep van de uitvoerder → exact één toegewezen locatie.
     *
     * @param  array<int, int>  $unitLocations
     * @param  array<int, int>  $workerUnits
     * @param  array<int, list<int>>  $workerLocations
     */
    private function resolveLocationId(PlannedShift $shift, array $unitLocations, array $workerUnits, array $workerLocations): ?int
    {
        if ($shift->location_id !== null) {
            return (int) $shift->location_id;
        }

        if ($shift->unit_id !== null && isset($unitLocations[(int) $shift->unit_id])) {
            return $unitLocations[(int) $shift->unit_id];
        }

        $defaultUnit = $workerUnits[(int) $shift->worker_id] ?? null;
        if ($defaultUnit !== null && isset($unitLocations[$defaultUnit])) {
            return $unitLocations[$defaultUnit];
        }

        $assigned = $workerLocations[(int) $shift->worker_id] ?? [];

        return count($assigned) === 1 ? $assigned[0] : null;
    }
}
