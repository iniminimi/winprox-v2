<?php

namespace App\Actions\Esg;

use App\Models\EsgIndicator;
use App\Support\Audit\AuditRecorder;

class SetEsgIndicatorActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(EsgIndicator $indicator, bool $active, ?int $actorUserId = null): EsgIndicator
    {
        $indicator->update(['is_active' => $active]);

        $fresh = $indicator->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: $active ? 'esg_indicator.activated' : 'esg_indicator.deactivated',
            modelType: EsgIndicator::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'is_active' => $fresh->is_active,
            ],
        );

        return $fresh;
    }
}
