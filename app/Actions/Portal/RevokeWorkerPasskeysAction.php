<?php

namespace App\Actions\Portal;

use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use InvalidArgumentException;

/**
 * Trekt alle passkeys van een uitvoerder in (beheer). De worker valt terug op
 * icoon/PIN en kan daarna via de portalsessie opnieuw registreren.
 */
class RevokeWorkerPasskeysAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Worker $worker, int $tenantId, ?int $actorUserId = null): int
    {
        if ((int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        $count = $worker->passkeyCredentials()->delete();

        if ($count > 0) {
            $this->audit->record(
                userId: $actorUserId,
                tenantId: (int) $worker->tenant_id,
                action: 'worker.passkeys_revoked',
                modelType: Worker::class,
                modelId: (int) $worker->id,
                payload: [
                    'worker_id' => (int) $worker->id,
                    'credentials_revoked' => $count,
                ],
            );
        }

        return $count;
    }
}
