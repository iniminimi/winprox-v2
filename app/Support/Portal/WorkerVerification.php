<?php

namespace App\Support\Portal;

use App\Actions\Portal\ConfirmWorkerIconAction;
use App\Actions\Portal\FindWorkerByIconOnTeamAction;
use App\Models\InternalTeam;
use App\Models\Unit;
use App\Models\Worker;

/**
 * Per QR-bezoek moet de worker het juiste icoon kiezen vóór taak/melding-acties.
 * Clock Point herstelt verificatie via de device-cookie; unit-QR kan recent vertrouwen
 * herstellen voor hetzelfde toestel (~12u) na een geslaagde on-site icoonbevestiging.
 */
final class WorkerVerification
{
    /** Vertrouwensvenster voor unit-QR veldacties op een onthouden toestel (seconden). */
    public const UNIT_FIELD_TRUST_SECONDS = 43200;

    public static function sessionKey(int $teamId): string
    {
        return 'wp_portal_verified_team_'.$teamId;
    }

    public static function unitFieldTrustSessionKey(int $teamId): string
    {
        return 'wp_portal_unit_field_trust_'.$teamId;
    }

    public static function clearForTeam(int $teamId): void
    {
        session()->forget(self::sessionKey($teamId));
        WorkerIconGuard::clearSessionForTeam($teamId);
    }

    public static function clearUnitFieldTrustForTeam(int $teamId): void
    {
        session()->forget(self::unitFieldTrustSessionKey($teamId));
    }

    public static function markVerified(InternalTeam $team, Worker $worker): void
    {
        if (! self::workerBelongsToTeam($worker, $team)) {
            return;
        }

        session([
            self::sessionKey((int) $team->id) => [
                'worker_id' => (int) $worker->id,
                'verified_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public static function verifiedWorker(InternalTeam $team): ?Worker
    {
        $payload = session(self::sessionKey((int) $team->id));
        if (! is_array($payload) || empty($payload['worker_id'])) {
            return null;
        }

        $worker = Worker::where('is_active', true)->find((int) $payload['worker_id']);

        if ($worker === null || ! self::workerBelongsToTeam($worker, $team)) {
            self::clearForTeam((int) $team->id);

            return null;
        }

        return $worker;
    }

    /**
     * Bevestig het icoon voor dit team op het huidige QR-bezoek.
     * Geeft null terug bij een verkeerd/onbekend icoon.
     */
    public static function confirmIcon(InternalTeam $team, string $iconSlug): ?Worker
    {
        $worker = app(FindWorkerByIconOnTeamAction::class)->handle($team, $iconSlug);
        if ($worker === null) {
            return null;
        }

        return self::confirmIconForWorker($team, $worker, $iconSlug);
    }

    /**
     * Bevestig het icoon van een worker die al via naam op dit toestel is gekoppeld.
     * Ondersteunt dezelfde icoon-keuze bij meerdere collega's (geen team-brede uniciteit).
     */
    public static function confirmIconForWorker(InternalTeam $team, Worker $worker, string $iconSlug): ?Worker
    {
        $confirmed = app(ConfirmWorkerIconAction::class)->handle($team, $worker, $iconSlug);
        if ($confirmed === null) {
            return null;
        }

        self::markVerified($team, $confirmed);
        WorkerIconGuard::clearAfterSuccessfulSignIn($team, $confirmed);

        return $confirmed;
    }

    /**
     * Na een geslaagd icoon op het unit-QR portaal: onthoud dit toestel ~12u
     * voor on-site taakacties.
     */
    public static function establishUnitFieldTrust(InternalTeam $team, Worker $worker): void
    {
        if (! self::workerBelongsToTeam($worker, $team)) {
            return;
        }

        self::markVerified($team, $worker);

        session([
            self::unitFieldTrustSessionKey((int) $team->id) => [
                'worker_id' => (int) $worker->id,
                'trusted_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Herstel bezoekverificatie op unit-QR wanneer deze browser recent on-site
     * zijn icoon bevestigde.
     */
    public static function restoreFromUnitFieldTrust(InternalTeam $team, Unit $unit): ?Worker
    {
        $already = self::verifiedWorker($team);
        if ($already !== null) {
            return $already;
        }

        $payload = session(self::unitFieldTrustSessionKey((int) $team->id));
        if (! is_array($payload) || empty($payload['worker_id']) || empty($payload['trusted_at'])) {
            return null;
        }

        $trustedAt = strtotime((string) $payload['trusted_at']);
        if ($trustedAt === false || (time() - $trustedAt) > self::UNIT_FIELD_TRUST_SECONDS) {
            self::clearUnitFieldTrustForTeam((int) $team->id);

            return null;
        }

        $deviceWorker = WorkerDeviceSession::rememberedWorkerOnTeam($team);
        if ($deviceWorker === null || (int) $deviceWorker->id !== (int) $payload['worker_id']) {
            return null;
        }

        if (! WorkerDeviceSession::workerCanActOnUnit($deviceWorker, $unit)) {
            self::clearUnitFieldTrustForTeam((int) $team->id);

            return null;
        }

        self::markVerified($team, $deviceWorker);

        return $deviceWorker;
    }

    private static function workerBelongsToTeam(Worker $worker, InternalTeam $team): bool
    {
        return (int) $worker->internal_team_id === (int) $team->id;
    }
}
