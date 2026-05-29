<?php

namespace App\Actions\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterTenantAction
{
    /**
     * Maakt een nieuwe organisatie (Tenant) + een beheerder-gebruiker die erbij hoort.
     *
     * NB: de users-tabel kent geen aparte 'role'-kolom; een tenant-gebruiker is per
     * definitie beheerder. De platform-superuser (is_superuser) staat hier los van en
     * wordt hier nooit aangemaakt.
     *
     * @param  array<string, mixed>  $data  organization, name, email, password
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['organization'],
            ]);

            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_superuser' => false,
            ]);
        });
    }
}
