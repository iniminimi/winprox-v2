<?php

namespace App\Actions\Auth;

use App\Actions\Billing\StartTenantTrialAction;
use App\Mail\NewTenantRegisteredMail;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RegisterTenantAction
{
    public function __construct(
        private StartTenantTrialAction $startTrial,
        private SendUserEmailVerificationAction $sendVerification,
    ) {}

    /**
     * Maakt een nieuwe organisatie (Tenant) + de eigenaar-gebruiker (rol admin).
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): User
    {
        $locale = LocaleSupport::normalize($data['locale'] ?? null);

        $countryCode = filled($data['country_code'] ?? null)
            ? strtoupper((string) $data['country_code'])
            : null;

        [$tenant, $user] = DB::transaction(function () use ($data, $locale, $countryCode) {
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

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'locale' => $locale,
                'password' => Hash::make($data['password']),
                'is_superuser' => false,
                'role' => User::ROLE_ADMIN,
            ]);

            return [$tenant, $user];
        });

        $this->sendVerification->handle($user);

        $to = config('winprox.new_tenant_notification_email');

        if ($to) {
            Mail::to($to)->send(new NewTenantRegisteredMail($tenant, $user));
        }

        return $user;
    }
}
