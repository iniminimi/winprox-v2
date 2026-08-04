<?php

declare(strict_types=1);

namespace App\Actions\Issues;

use App\Models\Issue;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zet de geordende stop-lijst van een inspectieronde (≥2 units).
 *
 * @param  list<int>  $unitIds  Geordende unit-id's (uniek).
 */
class SyncIssueRoundStopsAction
{
    /**
     * @param  list<int>  $unitIds
     */
    public function handle(Issue $issue, array $unitIds, User $actor): Issue
    {
        $uniqueIds = array_values(array_unique(array_map('intval', $unitIds)));

        if (count($uniqueIds) < 2) {
            throw ValidationException::withMessages([
                'round_stop_unit_ids' => [__('issues.errors.round_stops_min')],
            ]);
        }

        $units = Unit::query()
            ->where('tenant_id', $actor->tenant_id)
            ->whereIn('id', $uniqueIds)
            ->get()
            ->keyBy('id');

        if ($units->count() !== count($uniqueIds)) {
            throw ValidationException::withMessages([
                'round_stop_unit_ids' => [__('issues.errors.round_stops_invalid')],
            ]);
        }

        $hasDisabledUnitChecks = $units->contains(
            fn (Unit $unit) => ! $unit->allowsUnitChecks()
        );
        if ($hasDisabledUnitChecks) {
            throw ValidationException::withMessages([
                'round_stop_unit_ids' => [__('issues.errors.round_stops_unit_checks_required')],
            ]);
        }

        $locationIds = $units->pluck('location_id')->unique()->values();

        return DB::transaction(function () use ($issue, $uniqueIds, $locationIds) {
            $issue->roundStops()->delete();

            foreach ($uniqueIds as $index => $unitId) {
                $issue->roundStops()->create([
                    'unit_id' => $unitId,
                    'sort_order' => $index,
                ]);
            }

            $issue->forceFill([
                'unit_id' => null,
                'location_id' => $locationIds->count() === 1 ? (int) $locationIds->first() : null,
                'esg_indicator_id' => null,
            ])->save();

            return $issue->fresh(['roundStops.unit', 'location', 'unit']);
        });
    }
}
