<?php

namespace App\Support\Portal;

use App\Models\InternalTeam;
use App\Models\Worker;
use Illuminate\Support\Collection;

/**
 * Bepaalt welke worker-aanmeldstap het unit-QR portaal toont. Anonieme burgers
 * mogen nooit worker-UI zien: alleen veldtoestellen (cookie/onthouden/geverifieerd).
 */
final class UnitSignInPhase
{
    public const PHASE_NONE = 'none';
    public const PHASE_NO_TEAM = 'no_team';
    public const PHASE_NO_WORKERS = 'no_workers';
    public const PHASE_BLOCKED = 'blocked';
    public const PHASE_IDENTIFY = 'identify';
    public const PHASE_VERIFY = 'verify';
    public const PHASE_WRONG_TEAM = 'wrong_team';

    public static function isFieldWorkerVisitor(bool $canAct, ?Worker $deviceWorker): bool
    {
        if ($canAct || $deviceWorker instanceof Worker) {
            return true;
        }

        return WorkerDeviceSession::deviceTokenFromRequest() !== '';
    }

    public static function activeWorkerCountOnTeam(?InternalTeam $team): int
    {
        return $team instanceof InternalTeam ? $team->activeWorkerCount() : 0;
    }

    public static function resolvePhase(
        bool $canAct,
        bool $hasUnitTeam,
        int $activeTeamWorkerCount,
        bool $iconSignInBlocked,
        ?Worker $deviceWorker,
        ?Worker $anyDeviceWorker = null,
    ): string {
        if ($canAct) {
            return self::PHASE_NONE;
        }

        if (! $hasUnitTeam) {
            return self::PHASE_NO_TEAM;
        }

        if ($iconSignInBlocked) {
            return self::PHASE_BLOCKED;
        }

        if ($activeTeamWorkerCount === 0) {
            return self::PHASE_NO_WORKERS;
        }

        if ($anyDeviceWorker instanceof Worker && $deviceWorker === null) {
            return self::PHASE_WRONG_TEAM;
        }

        if ($deviceWorker instanceof Worker) {
            return self::PHASE_VERIFY;
        }

        return self::PHASE_IDENTIFY;
    }

    /**
     * @param  Collection<int, \App\Models\Task>  $tasks
     */
    public static function tasksNeedFieldWorker(Collection $tasks): bool
    {
        foreach ($tasks as $task) {
            if ($task->isOpen()) {
                return true;
            }
        }

        return false;
    }
}
