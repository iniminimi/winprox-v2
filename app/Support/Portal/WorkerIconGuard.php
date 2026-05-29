<?php

namespace App\Support\Portal;

use App\Models\InternalTeam;
use App\Models\Worker;

/**
 * Beperkt foute icoon-aanmeldpogingen per QR-bezoek (sessie) én per bekend
 * toestel (worker-rij). Na 2 foute pogingen is de worker geblokkeerd tot een
 * beheerder ontgrendelt of het icoon reset.
 */
final class WorkerIconGuard
{
    public const MAX_FAILED_ATTEMPTS = 2;

    public static function attemptsSessionKey(int $teamId): string
    {
        return 'wp_portal_icon_failed_attempts_team_'.$teamId;
    }

    public static function blockedSessionKey(int $teamId): string
    {
        return 'wp_portal_icon_blocked_team_'.$teamId;
    }

    public static function clearSessionForTeam(int $teamId): void
    {
        session()->forget([
            self::attemptsSessionKey($teamId),
            self::blockedSessionKey($teamId),
        ]);
    }

    public static function isBlocked(InternalTeam $team): bool
    {
        if (session(self::blockedSessionKey((int) $team->id)) === true) {
            return true;
        }

        $worker = WorkerDeviceSession::rememberedWorkerOnTeam($team);

        return $worker !== null && $worker->field_icon_locked_at !== null;
    }

    public static function remainingAttempts(InternalTeam $team): int
    {
        if (self::isBlocked($team)) {
            return 0;
        }

        $used = (int) session(self::attemptsSessionKey((int) $team->id), 0);

        return max(0, self::MAX_FAILED_ATTEMPTS - $used);
    }

    public static function recordFailedAttempt(InternalTeam $team): void
    {
        $teamId = (int) $team->id;
        $attempts = (int) session(self::attemptsSessionKey($teamId), 0) + 1;
        session([self::attemptsSessionKey($teamId) => $attempts]);

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            session([self::blockedSessionKey($teamId) => true]);
        }

        $worker = WorkerDeviceSession::rememberedWorkerOnTeam($team);
        if ($worker === null) {
            return;
        }

        $failed = (int) $worker->field_icon_failed_attempts + 1;
        $worker->forceFill(['field_icon_failed_attempts' => $failed]);

        if ($failed >= self::MAX_FAILED_ATTEMPTS) {
            $worker->forceFill(['field_icon_locked_at' => now()]);
        }

        $worker->save();
    }

    public static function clearAfterSuccessfulSignIn(InternalTeam $team, Worker $worker): void
    {
        self::clearSessionForTeam((int) $team->id);

        $worker->forceFill([
            'field_icon_failed_attempts' => 0,
            'field_icon_locked_at' => null,
        ])->save();
    }

    public static function unlockWorker(Worker $worker): void
    {
        $worker->forceFill([
            'field_icon_failed_attempts' => 0,
            'field_icon_locked_at' => null,
        ])->save();

        if ($worker->internal_team_id !== null) {
            self::clearSessionForTeam((int) $worker->internal_team_id);
        }
    }
}
