<?php

namespace App\Actions\Locations;

use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Schema;

class UpdateUnitAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Unit $unit, array $data, ?int $actorUserId = null): Unit
    {
        $payload = [
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'default_internal_team_id' => $data['default_internal_team_id'] ?? null,
        ];

        if (Schema::hasColumn('units', 'category_id')) {
            $payload['category_id'] = $data['category_id'] ?? null;
        }

        $unit->update($payload);

        $fresh = $unit->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'unit.updated',
            modelType: Unit::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name],
        );

        return $fresh;
    }
}
