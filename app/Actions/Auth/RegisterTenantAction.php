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
                'phone' => isset($data['phone']) && trim($data['phone']) !== '' ? trim($data['phone']) : null,
                'street' => isset($data['street']) && trim($data['street']) !== '' ? trim($data['street']) : null,
                'house_number' => isset($data['house_number']) && trim($data['house_number']) !== '' ? trim($data['house_number']) : null,
                'postal_code' => isset($data['postal_code']) && trim($data['postal_code']) !== '' ? trim($data['postal_code']) : null,
                'city' => isset($data['city']) && trim($data['city']) !== '' ? trim($data['city']) : null,
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
