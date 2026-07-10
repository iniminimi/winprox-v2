<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;

class ActivateUnitAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureUnitTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    public function handle(Unit $unit, ?int $actorUserId = null): Unit
    {
        $unit->update(['is_active' => true]);

        $fresh = $unit->fresh();

        $this->ensureTranslationSlots->handle($fresh);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'unit.activated',
            modelType: Unit::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name],
        );

        return $fresh;
    }
}
