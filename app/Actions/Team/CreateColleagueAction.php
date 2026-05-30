<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Password;

/**
 * Maakt een collega-gebruiker (beheerder) in de opgegeven tenant aan.
 * Wachtwoord wordt door de beheerder ingesteld; optioneel accountmail met reset-link.
 *
 * Integration-first (§3.0): tenant expliciet als parameter, geen globale state.
 *
 * @phpstan-param array{name: string, email: string, role: string, locale: string, password: string, send_account_email?: bool} $data
 */
class CreateColleagueAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $tenantId, ?int $actorUserId = null): User
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->assertCanAddUsers(1);

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'email' => $data['email'],
            'locale' => $data['locale'],
            'role' => $data['role'] ?? User::ROLE_EMPLOYEE,
            'password' => $data['password'],
            'is_superuser' => false,
            'is_active' => true,
        ]);

        if (! empty($data['send_account_email'])) {
            Password::sendResetLink(['email' => $user->email]);
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'user.colleague_created',
            modelType: User::class,
            modelId: (int) $user->id,
            payload: ['id' => $user->id, 'email' => $user->email, 'role' => $user->role, 'locale' => $user->locale],
        );

        return $user;
    }
}
