<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\StoreWorkerPasskeyCredentialAction;
use App\Actions\Portal\TouchWorkerPasskeyCredentialAction;
use App\Actions\Time\AssertWorkerClockDeviceAction;
use App\Models\Worker;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Portal\WorkerPasskeyCeremony;
use App\Support\Portal\WorkerVerification;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * WebAuthn/passkey endpoints voor het time-portaal. Registratie vereist een al
 * geverifieerde portalsessie; assertion verifieert een onthouden worker en
 * eindigt via hetzelfde pad als icoon/PIN (device-binding + markVerified).
 */
class WorkerPasskeyController
{
    public function registerOptions(Request $request, int $tenant): JsonResponse
    {
        Tenancy::actAs($tenant);

        $worker = $this->verifiedPortalWorker($tenant);
        abort_if($worker === null, 403);

        return response()->json(WorkerPasskeyCeremony::issueRegistrationOptions($worker, $request));
    }

    public function register(Request $request, int $tenant): JsonResponse
    {
        Tenancy::actAs($tenant);

        $worker = $this->verifiedPortalWorker($tenant);
        abort_if($worker === null, 403);

        $credentialJson = (string) $request->input('credential', '');

        try {
            $record = WorkerPasskeyCeremony::verifyRegistration($worker, $credentialJson, $request);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'error' => 'failed'], 422);
        }

        app(StoreWorkerPasskeyCredentialAction::class)->handle($worker, $record);

        return response()->json(['ok' => true]);
    }

    public function assertOptions(Request $request, int $tenant): JsonResponse
    {
        Tenancy::actAs($tenant);

        $worker = $this->rememberedPortalWorker($tenant);
        abort_if($worker === null, 403);
        abort_if(! $worker->passkeyCredentials()->exists(), 404);

        return response()->json(WorkerPasskeyCeremony::issueAssertionOptions($worker, $request));
    }

    public function assert(Request $request, int $tenant): JsonResponse
    {
        Tenancy::actAs($tenant);

        $worker = $this->rememberedPortalWorker($tenant);
        abort_if($worker === null, 403);

        $credentialJson = (string) $request->input('credential', '');

        try {
            [$credential, $record] = WorkerPasskeyCeremony::verifyAssertion($worker, $credentialJson, $request);
        } catch (\Throwable) {
            return response()->json(['ok' => false, 'error' => 'failed'], 422);
        }

        app(TouchWorkerPasskeyCredentialAction::class)->handle($credential, $record->counter);

        if (! $this->bindAndMarkVerified($worker, $tenant)) {
            return response()->json(['ok' => false, 'error' => 'device_mismatch'], 409);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Onthouden worker van dit toestel binnen de tenant (cookie/sessie).
     */
    private function rememberedPortalWorker(int $tenantId): ?Worker
    {
        return WorkerDeviceSession::rememberedWorkerForTenant($tenantId);
    }

    /**
     * Onthouden worker die in deze sessie ook al via icoon/PIN geverifieerd is.
     */
    private function verifiedPortalWorker(int $tenantId): ?Worker
    {
        $worker = $this->rememberedPortalWorker($tenantId);
        $team = $worker?->team;
        if ($worker === null || $team === null) {
            return null;
        }

        $verified = WorkerVerification::verifiedWorker($team);

        return $verified !== null && (int) $verified->id === (int) $worker->id ? $verified : null;
    }

    /**
     * Zelfde afronding als icoon/PIN-verificatie: gsm-binding afdwingen en de
     * portalsessie als geverifieerd markeren.
     */
    private function bindAndMarkVerified(Worker $worker, int $tenantId): bool
    {
        $worker->refresh();

        $token = WorkerDeviceSession::deviceTokenFromRequest();
        $device = $worker->devices()->where('device_token', $token)->first();

        try {
            $bound = app(AssertWorkerClockDeviceAction::class)->handle(
                $worker,
                $device,
                $tenantId,
                $token,
                true,
            );
        } catch (InvalidArgumentException) {
            $team = $worker->team;
            if ($team !== null) {
                WorkerVerification::clearForTeam((int) $team->id);
            }

            return false;
        }

        WorkerDeviceSession::persistDeviceToken($bound->device_token, $worker->team);
        WorkerDeviceSession::bindRememberedWorkerForTenant($worker);

        $team = $worker->team;
        if ($team !== null) {
            WorkerVerification::markVerified($team, $worker);
        }

        return true;
    }
}
