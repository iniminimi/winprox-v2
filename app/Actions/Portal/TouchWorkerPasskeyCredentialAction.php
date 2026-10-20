<?php

namespace App\Actions\Portal;

use App\Models\WorkerPasskeyCredential;

/**
 * Werkt sign-count + last_used bij na een geslaagde assertion.
 * Niet-stijgende counters (gesynchroniseerde passkeys geven vaak 0) zijn oké;
 * een lagere counter dan opgeslagen overschrijft nooit de hoogste waarde.
 */
class TouchWorkerPasskeyCredentialAction
{
    public function handle(WorkerPasskeyCredential $credential, int $signCount): void
    {
        $credential->forceFill([
            'sign_count' => max((int) $credential->sign_count, $signCount),
            'last_used_at' => now(),
        ])->save();
    }
}
