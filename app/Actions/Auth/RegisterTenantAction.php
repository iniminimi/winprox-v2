<?php

namespace App\Actions\Auth;

use App\Actions\Billing\StartTenantTrialAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterTenantAction
{
    public function __construct(private StartTenantTrialAction $startTrial) {}
    /**
     * Maakt een nieuwe organisatie (Tenant) + de eigenaar-gebruiker (rol admin).
     *
     * De platform-superuser (is_superuser) staat hier los van en wordt hier nooit
     * aangemaakt.
     *
     * @param  array<string, mixed>  $data  organization, name, email, password
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['organization'],
            ]);

            $this->startTrial->handle($tenant);

            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_superuser' => false,
                'role' => User::ROLE_ADMIN,
            ]);
        });
    }
}
