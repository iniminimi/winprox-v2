<?php

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Maakt een collega-gebruiker (beheerder) in de opgegeven tenant aan en stuurt
 * een account-/welkomstmail met een set-wachtwoord-link via de password broker
 * (hergebruikt de bestaande reset-flow).
 *
 * Integration-first (§3.0): tenant expliciet als parameter, geen globale state.
 *
 * @phpstan-param array{name: string, email: string, role: string} $data
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
            'role' => $data['role'] ?? User::ROLE_EMPLOYEE,
            // Tijdelijk willekeurig wachtwoord; de gebruiker stelt zijn eigen
            // wachtwoord in via de set-wachtwoord-link uit de mail.
            'password' => Str::random(40),
            'is_superuser' => false,
            'is_active' => true,
        ]);

        Password::sendResetLink(['email' => $user->email]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'user.colleague_created',
            modelType: User::class,
            modelId: (int) $user->id,
            payload: ['id' => $user->id, 'email' => $user->email, 'role' => $user->role],
        );

        return $user;
    }
}
