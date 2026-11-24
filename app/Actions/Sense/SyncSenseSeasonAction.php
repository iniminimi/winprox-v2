<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Models\SensePoint;
use App\Support\Audit\AuditRecorder;
use App\Support\Sense\SenseSeason;

class SyncSenseSeasonAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handleAll(): int
    {
        $updated = 0;

        $tenantIds = SensePoint::query()
            ->withoutGlobalScopes()
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $updated += $this->handleTenant((int) $tenantId);
        }

        return $updated;
    }

    public function handleTenant(int $tenantId): int
    {
        $season = SenseSeason::current();
        $updated = 0;

        $points = SensePoint::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get();

        foreach ($points as $point) {
            if ($point->season_applied === $season) {
                continue;
            }

            $this->apply($point, $season, $tenantId);
            $updated++;
        }

        return $updated;
    }

    private function apply(SensePoint $point, string $season, int $tenantId): void
    {
        $values = config('sense.presets.'.$season);

        $changes = [
            'temperature_low' => round((float) $values['temperature']['low'], 1),
            'temperature_target' => round((float) $values['temperature']['target'], 1),
            'temperature_high' => round((float) $values['temperature']['high'], 1),
            'humidity_low' => round((float) $values['humidity']['low'], 1),
            'humidity_target' => round((float) $values['humidity']['target'], 1),
            'humidity_high' => round((float) $values['humidity']['high'], 1),
            'season_applied' => $season,
        ];

        $point->fill($changes);
        $point->save();

        $this->audit->record(
            userId: null,
            tenantId: $tenantId,
            action: 'sense_point.bands_updated',
            modelType: SensePoint::class,
            modelId: (int) $point->id,
            payload: ['preset' => $season, 'automatic' => true] + $changes,
        );
    }
}
