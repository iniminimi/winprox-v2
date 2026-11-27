<?php

namespace App\Actions\Auth;

use App\Models\BackofficeLoginChallenge;
use App\Models\User;
use App\Models\UserPasskeyCredential;
use App\Support\Audit\AuditRecorder;
use App\Support\Auth\UserPasskeyCeremony;
use InvalidArgumentException;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * Bewaart de publieke sleutel nadat de telefoon het wachtwoord al bewees.
 */
class StoreUserPasskeyCredentialAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  list<string>  $origins
     */
    public function handle(
        string $token,
        string $credentialJson,
        string $rpId,
        array $origins,
        string $deviceLabel,
    ): void {
        $challenge = BackofficeLoginChallenge::findByPlainToken($token);
        if ($challenge === null || ! $challenge->isOpen()
            || $challenge->status !== BackofficeLoginChallenge::STATUS_AUTHENTICATED
            || $challenge->registration_options === null
        ) {
            throw new InvalidArgumentException('expired');
        }

        $options = $challenge->registration_options;
        $challenge->update(['registration_options' => null]);
        $challenge->loadMissing('user.passkeyCredential');
        $user = $challenge->user;
        if (! $user instanceof User) {
            throw new InvalidArgumentException('expired');
        }

        if ($user->passkeyCredential !== null && ! $user->phone_reregistration_open) {
            throw new InvalidArgumentException('failed');
        }

        $record = UserPasskeyCeremony::verifyRegistration($user, $credentialJson, $options, $rpId, $origins);
        $revoked = $user->phone_revoked_label;

        if ($user->passkeyCredential !== null) {
            $user->passkeyCredential->delete();
        }

        UserPasskeyCredential::query()->create([
            'tenant_id' => (int) $user->tenant_id,
            'user_id' => (int) $user->id,
            'credential_id' => Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId),
            'credential_public_key' => base64_encode($record->credentialPublicKey),
            'user_handle' => $record->userHandle,
            'transports' => $record->transports,
            'aaguid' => $record->aaguid->toRfc4122(),
            'backup_eligible' => $record->backupEligible,
            'backup_status' => $record->backupStatus,
            'sign_count' => $record->counter,
            'device_label' => $deviceLabel,
            'last_used_at' => now(),
        ]);

        $user->update([
            'phone_reregistration_open' => false,
            'phone_revoked_label' => null,
        ]);

        $challenge->update(['status' => BackofficeLoginChallenge::STATUS_APPROVED]);

        $this->audit->record(
            userId: (int) $user->id,
            tenantId: (int) $user->tenant_id,
            action: 'auth.phone_registered',
            modelType: User::class,
            modelId: (int) $user->id,
            payload: [
                'user_id' => (int) $user->id,
                'user_name' => $user->name,
                'device_label' => $deviceLabel,
                'revoked_device_label' => $revoked,
            ],
        );
    }
}
