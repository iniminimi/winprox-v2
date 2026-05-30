<?php

namespace App\Actions\Team;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

/**
 * Beheerder-"unlock": wist het persoonlijke icoon van de worker, reset de
 * lockout-teller/-tijd en verwijdert de gekoppelde veldtoestellen. Daarna moet
 * de worker zich op de werkvloer opnieuw identificeren en een icoon kiezen.
 */
class ResetWorkerIconAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, ?int $actorUserId = null): Worker
    {
        $worker->devices()->delete();

        $worker->forceFill([
            'field_icon_slug' => null,
            'field_icon_failed_attempts' => 0,
            'field_icon_locked_at' => null,
        ])->save();

        $fresh = $worker->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'worker.icon_reset',
            modelType: Worker::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id],
        );

        return $fresh;
    }
}
