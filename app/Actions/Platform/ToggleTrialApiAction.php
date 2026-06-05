<?php

namespace App\Actions\Platform;

use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;

class ToggleTrialApiAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Tenant $tenant, ?int $actorUserId = null): void
    {
        $newValue = ! $tenant->allow_trial_api;
        
        $tenant->update(['allow_trial_api' => $newValue]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $tenant->id,
            action: 'tenant.trial_api_toggled',
            modelType: Tenant::class,
            modelId: (int) $tenant->id,
            payload: ['allow_trial_api' => $newValue],
        );
    }
}
