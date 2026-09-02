<?php

namespace App\Actions\Team;

use App\Actions\Locations\SyncUserLocationsAction;
use App\Mail\WelcomeAccountMail;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

/**
 * Maakt een collega-gebruiker (beheerder) in de opgegeven tenant aan.
 * Wachtwoord wordt door de beheerder ingesteld; optioneel accountmail met reset-link.
 *
 * Integration-first (§3.0): tenant expliciet als parameter, geen globale state.
 *
 * @phpstan-param array{name: string, email: string, role: string, locale: string, password: string, send_account_email?: bool, location_ids?: list<int>, punch_clock_team_id?: int} $data
 */
class CreateColleagueAction
{
    public function __construct(
        private AuditRecorder $audit,
        private SyncUserLocationsAction $syncLocations,
        private CreateLinkedWorkerForUserAction $createLinkedWorker,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $tenantId, ?int $actorUserId = null): User
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->assertCanAddSeats(1);

        $role = $data['role'] ?? User::ROLE_EMPLOYEE;

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'email' => $data['email'],
            'locale' => $data['locale'],
            'role' => $role,
            'password' => $data['password'],
            'notify_on_new_issue_email' => array_key_exists('notify_on_new_issue_email', $data)
                ? (bool) $data['notify_on_new_issue_email']
                : true,
            'is_superuser' => false,
            'is_active' => true,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        if ($role === User::ROLE_EMPLOYEE && array_key_exists('location_ids', $data)) {
            $this->syncLocations->handle($user, $data['location_ids'] ?? [], $actorUserId);
        }

        if (! empty($data['punch_clock_team_id'])) {
            $team = InternalTeam::query()
                ->where('tenant_id', $tenantId)
                ->findOrFail((int) $data['punch_clock_team_id']);

            $workerLocationIds = $data['worker_location_ids'] ?? $data['location_ids'] ?? [];
            $this->createLinkedWorker->handle($user, $team, $workerLocationIds, $actorUserId);
        }

        if (! empty($data['send_account_email'])) {
            $token = Password::broker()->createToken($user);

            /** @var User|null $admin */
            $admin = $actorUserId !== null ? User::query()->find($actorUserId) : null;

            if ($admin !== null) {
                Mail::to($user->email)->send(new WelcomeAccountMail(
                    user: $user,
                    tenant: $tenant,
                    admin: $admin,
                    resetToken: $token,
                ));
            }
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'user.colleague_created',
            modelType: User::class,
            modelId: (int) $user->id,
            payload: ['id' => $user->id, 'email' => $user->email, 'role' => $user->role, 'locale' => $user->locale, 'notify_on_new_issue_email' => $user->notify_on_new_issue_email],
        );

        return $user->fresh(['locations', 'linkedWorker']);
    }
}
