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
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): User
    {
        $locale = in_array($data['locale'] ?? '', ['nl', 'en', 'fr', 'de'], true)
            ? $data['locale']
            : 'nl';

        $countryCode = filled($data['country_code'] ?? null)
            ? strtoupper((string) $data['country_code'])
            : null;

        return DB::transaction(function () use ($data, $locale, $countryCode) {
            $tenant = Tenant::create([
                'name' => trim((string) $data['organization']),
                'email' => $data['email'],
                'phone' => filled($data['phone'] ?? null) ? $data['phone'] : null,
                'street' => filled($data['street'] ?? null) ? $data['street'] : null,
                'house_number' => filled($data['house_number'] ?? null) ? $data['house_number'] : null,
                'postal_code' => filled($data['postal_code'] ?? null) ? $data['postal_code'] : null,
                'city' => filled($data['city'] ?? null) ? $data['city'] : null,
                'country_code' => $countryCode,
            ]);

            $this->startTrial->handle($tenant);

            return User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'locale' => $locale,
                'password' => Hash::make($data['password']),
                'is_superuser' => false,
                'role' => User::ROLE_ADMIN,
            ]);
        });
    }
}
