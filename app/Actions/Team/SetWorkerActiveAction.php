<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

class SetWorkerActiveAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, bool $active, ?int $actorUserId = null): Worker
    {
        if ($active && ! $worker->is_active) {
            $linkedUserActive = $worker->user_id !== null
                && User::query()->whereKey($worker->user_id)->where('is_active', true)->exists();

            if (! $linkedUserActive) {
                Tenant::query()->findOrFail($worker->tenant_id)->assertCanAddSeats(1);
            }
        }

        $worker->update(['is_active' => $active]);

        $fresh = $worker->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'worker.updated',
            modelType: Worker::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'is_active' => $fresh->is_active],
        );

        return $fresh;
    }
}
