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
 * - Effectieve locatie: planned_shifts.location_id, anders exact één
 *   toegewezen locatie van de uitvoerder; anders telt de dienst nergens
 *   (bekende v1-beperking bij 0 of >=2 toegewezen locaties).
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

        $locations = Location::query()
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

        return ['total' => $total, 'locations' => array_values($groups)];
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
            ->where('tenant_id', $tenantId)
            ->where('kind', ShiftTypeKind::Work->value)
            ->whereIn('work_date', $dates)
            ->whereIn('shift_type_id', $typeIds)
            ->get(['worker_id', 'work_date', 'shift_type_id', 'location_id']);

        if ($shifts->isEmpty()) {
            return [];
        }

        // worker_id => [location_ids]; effectieve locatie-fallback enkel bij exact één.
        $workerLocations = DB::table('location_worker')
            ->whereIn('worker_id', $shifts->pluck('worker_id')->unique())
            ->get(['worker_id', 'location_id'])
            ->groupBy('worker_id')
            ->map(fn ($rows) => $rows->pluck('location_id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $seen = [];
        foreach ($shifts as $shift) {
            $locationId = $shift->location_id !== null
                ? (int) $shift->location_id
                : (count($workerLocations[$shift->worker_id] ?? []) === 1
                    ? $workerLocations[$shift->worker_id][0]
                    : null);

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
}
