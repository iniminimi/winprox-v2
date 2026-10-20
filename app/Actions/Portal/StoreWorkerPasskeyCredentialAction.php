<?php

namespace App\Actions\Portal;

use App\Models\Worker;
use App\Models\WorkerPasskeyCredential;
use App\Support\Audit\AuditRecorder;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\CredentialRecord;

/**
 * Legt een gevalideerde WebAuthn-attestation vast bij de worker.
 * credential_id is uniek; herregistratie ververst sleutel + counter.
 */
class StoreWorkerPasskeyCredentialAction
{
    public function __construct(
        private AuditRecorder $audit,
    ) {}

    public function handle(Worker $worker, CredentialRecord $record): WorkerPasskeyCredential
    {
        $credential = WorkerPasskeyCredential::query()->updateOrCreate(
            ['credential_id' => Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId)],
            [
                'tenant_id' => (int) $worker->tenant_id,
                'worker_id' => (int) $worker->id,
                'credential_public_key' => base64_encode($record->credentialPublicKey),
                'user_handle' => $record->userHandle,
                'transports' => $record->transports,
                'aaguid' => $record->aaguid->toRfc4122(),
                'backup_eligible' => $record->backupEligible,
                'backup_status' => $record->backupStatus,
                'sign_count' => $record->counter,
                'last_used_at' => now(),
            ],
        );

        $this->audit->record(
            userId: null,
            tenantId: (int) $worker->tenant_id,
            action: 'worker.passkey_registered',
            modelType: Worker::class,
            modelId: (int) $worker->id,
            payload: [
                'worker_id' => (int) $worker->id,
                'credential_id' => $credential->credential_id,
                'backup_eligible' => $record->backupEligible,
            ],
        );

        return $credential;
    }
}
