<?php

namespace App\Actions\Team;

use App\Actions\Locations\SyncUserLocationsAction;
use App\Models\InternalTeam;
use App\Models\User;
use App\Support\Audit\AuditRecorder;

class UpdateColleagueAction
{
    public function __construct(
        private AuditRecorder $audit,
        private SyncUserLocationsAction $syncLocations,
        private CreateLinkedWorkerForUserAction $createLinkedWorker,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, array $data, ?int $actorUserId = null): User
    {
        $role = $data['role'] ?? $user->role;

        $attributes = [
            'name' => $data['name'],
            'email' => $data['email'],
            'locale' => $data['locale'],
            'role' => $role,
        ];

        if (array_key_exists('notify_on_new_issue_email', $data)) {
            $attributes['notify_on_new_issue_email'] = (bool) $data['notify_on_new_issue_email'];
        }

        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $user->update($attributes);

        if ($role === User::ROLE_EMPLOYEE && array_key_exists('location_ids', $data)) {
            $this->syncLocations->handle($user, $data['location_ids'] ?? [], $actorUserId);
        } elseif ($role === User::ROLE_ADMIN) {
            $this->syncLocations->handle($user, [], $actorUserId);
        }

        if (! empty($data['punch_clock_team_id'])) {
            $team = InternalTeam::query()
                ->where('tenant_id', $user->tenant_id)
                ->findOrFail((int) $data['punch_clock_team_id']);

            $workerLocationIds = $data['worker_location_ids'] ?? $data['location_ids'] ?? [];
            $linked = $this->createLinkedWorker->handle($user, $team, $workerLocationIds, $actorUserId);

            if ($linked->user_id === $user->id && array_key_exists('location_ids', $data)) {
                $this->syncLocations->handleForWorker($linked, $data['location_ids'] ?? [], $actorUserId);
            }
        } elseif ($user->linkedWorker !== null && array_key_exists('location_ids', $data)) {
            $this->syncLocations->handleForWorker($user->linkedWorker, $data['location_ids'] ?? [], $actorUserId);
        }

        $fresh = $user->fresh(['locations', 'linkedWorker']);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'user.colleague_updated',
            modelType: User::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'email' => $fresh->email, 'role' => $fresh->role, 'notify_on_new_issue_email' => $fresh->notify_on_new_issue_email],
        );

        return $fresh;
    }
}
