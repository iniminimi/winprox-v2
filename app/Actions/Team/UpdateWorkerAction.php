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

        $updates = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_external' => $isExternal,
            'company_name' => $companyName,
        ];

        if (array_key_exists('ssin', $data)) {
            $updates['ssin'] = self::normalizedSsin($data['ssin'] ?? null);
        }

        $worker->update($updates);

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

    private static function normalizedSsin(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) === 11 ? $digits : null;
    }
}
