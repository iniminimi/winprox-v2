<?php

namespace App\Support\Dashboard;

use App\Models\QrScan;
use App\Models\Unit;
use App\Models\UnitPortalVisit;
use Illuminate\Support\Collection;

final class TopScannedUnitsService
{
    public const PERIOD_DAYS = 7;

    public const LIMIT = 5;

    /**
     * @return list<TopScannedUnit>
     */
    public function topForCurrentTenant(): array
    {
        $since = now()->subDays(self::PERIOD_DAYS);

        /** @var Collection<int, int> $counts */
        $counts = collect();

        UnitPortalVisit::query()
            ->where('visited_at', '>=', $since)
            ->selectRaw('unit_id, COUNT(*) as visit_count')
            ->groupBy('unit_id')
            ->pluck('visit_count', 'unit_id')
            ->each(function (mixed $count, mixed $unitId) use ($counts): void {
                $id = (int) $unitId;
                $counts[$id] = ($counts[$id] ?? 0) + (int) $count;
            });

        QrScan::query()
            ->where('scanned_at', '>=', $since)
            ->join('qr_codes', 'qr_codes.id', '=', 'qr_scans.qr_code_id')
            ->whereNotNull('qr_codes.unit_id')
            ->selectRaw('qr_codes.unit_id as unit_id, COUNT(*) as visit_count')
            ->groupBy('qr_codes.unit_id')
            ->pluck('visit_count', 'unit_id')
            ->each(function (mixed $count, mixed $unitId) use ($counts): void {
                $id = (int) $unitId;
                $counts[$id] = ($counts[$id] ?? 0) + (int) $count;
            });

        if ($counts->isEmpty()) {
            return [];
        }

        $sortedUnitIds = $counts
            ->sortByDesc(fn (int $count): int => $count)
            ->keys()
            ->take(self::LIMIT)
            ->values();

        $units = Unit::query()
            ->whereIn('id', $sortedUnitIds)
            ->where('is_active', true)
            ->with('location:id,name')
            ->get()
            ->keyBy('id');

        $rows = [];

        foreach ($sortedUnitIds as $unitId) {
            $unit = $units->get($unitId);
            if ($unit === null) {
                continue;
            }

            $rows[] = new TopScannedUnit(
                unitId: (int) $unit->id,
                unitName: $unit->localizedName(),
                locationName: (string) ($unit->location?->localizedName() ?? __('dashboard.traffic.no_location')),
                scanCount: (int) $counts[$unitId],
                detailUrl: route('locations.show', [
                    'location' => $unit->location_id,
                    'unit_id' => $unit->id,
                ]),
                issuesUrl: route('issues.index', ['unit_id' => $unit->id]),
            );
        }

        return $rows;
    }
}
