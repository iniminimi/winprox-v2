<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Models\Unit;
use App\Support\Audit\AuditRecorder;

class UpdateUnitGpsAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        Unit $unit,
        float $latitude,
        float $longitude,
        int $tenantId,
        ?int $actorUserId = null
    ): Unit {
        $unit->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit.gps_updated',
            modelType: Unit::class,
            modelId: $unit->id,
            payload: [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'actor_user_id' => $actorUserId,
            ],
        );

        return $unit;
    }
}
