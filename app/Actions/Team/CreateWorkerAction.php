<?php

namespace App\Actions\Team;

use App\Actions\Locations\SyncUserLocationsAction;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;

/**
 * Voegt een worker (uitvoerder zonder login) toe aan een team. De worker
 * bevestigt later zelf een persoonlijk icoon via de Clock Point-QR.
 */
class CreateWorkerAction
{
    public function __construct(
        private AuditRecorder $audit,
        private SyncUserLocationsAction $syncLocations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(InternalTeam $team, array $data, ?int $actorUserId = null, ?Worker $actorWorker = null): Worker
    {
        if ($actorWorker !== null) {
            if (! $actorWorker->is_teamleader || ! $actorWorker->is_active) {
                throw new \InvalidArgumentException('not_teamleader');
            }

            if ((int) $actorWorker->internal_team_id !== (int) $team->id) {
                throw new \InvalidArgumentException('wrong_team');
            }
        }

        $tenant = Tenant::query()->findOrFail($team->tenant_id);

        $linkedUserId = isset($data['user_id']) ? (int) $data['user_id'] : null;
        $skipSeatCheck = $linkedUserId !== null
            && User::query()->whereKey($linkedUserId)->where('is_active', true)->exists();

        if (! $skipSeatCheck) {
            $tenant->assertCanAddSeats(1);
        }

        $companyName = self::normalizedCompanyName($data['company_name'] ?? null);
        $isExternal = $companyName !== null || (bool) ($data['is_external'] ?? false);

        $worker = Worker::create([
            'tenant_id' => $team->tenant_id,
            'user_id' => $linkedUserId,
            'internal_team_id' => $team->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'ssin' => self::normalizedSsin($data['ssin'] ?? null),
            'is_external' => $isExternal,
            'company_name' => $companyName,
            'is_active' => true,
        ]);

        if (array_key_exists('location_ids', $data)) {
            $this->syncLocations->handleForWorker($worker, $data['location_ids'] ?? [], $actorUserId);
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.created',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: array_merge(
                ['id' => $worker->id, 'internal_team_id' => $worker->internal_team_id],
                $actorWorker !== null ? ['actor_worker_id' => $actorWorker->id] : []
            ),
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
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        return strlen($digits) === 11 ? $digits : null;
    }
}
