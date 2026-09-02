<?php

namespace App\Actions\Team;

use App\Actions\Locations\SyncUserLocationsAction;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

class UpdateWorkerAction
{
    public function __construct(
        private AuditRecorder $audit,
        private SyncUserLocationsAction $syncLocations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Worker $worker, array $data, ?int $actorUserId = null): Worker
    {
        $companyName = self::normalizedCompanyName($data['company_name'] ?? null);
        $isExternal = $companyName !== null || (bool) ($data['is_external'] ?? false);

        $worker->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_external' => $isExternal,
            'company_name' => $companyName,
        ]);

        if (array_key_exists('location_ids', $data)) {
            $this->syncLocations->handleForWorker($worker, $data['location_ids'] ?? [], $actorUserId);
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.updated',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: ['id' => $worker->id, 'internal_team_id' => $worker->internal_team_id],
        );

        return $worker->fresh(['locations']);
    }

    private static function normalizedCompanyName(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
