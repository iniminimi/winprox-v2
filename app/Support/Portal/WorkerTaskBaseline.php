<?php

namespace App\Support\Portal;

/**
 * Sessie-baseline van open teamtaken na Clock Point-login.
 * Nieuwe taken = open/goedgekeurd en nog niet in deze set.
 */
final class WorkerTaskBaseline
{
    public static function sessionKey(int $teamId): string
    {
        return 'wp_portal_task_baseline_'.$teamId;
    }

    public static function clearForTeam(int $teamId): void
    {
        session()->forget(self::sessionKey($teamId));
    }

    /**
     * @return array{worker_id: int, task_ids: list<int>}|null
     */
    public static function payload(int $teamId): ?array
    {
        $payload = session(self::sessionKey($teamId));
        if (! is_array($payload) || empty($payload['worker_id']) || ! isset($payload['task_ids']) || ! is_array($payload['task_ids'])) {
            return null;
        }

        return [
            'worker_id' => (int) $payload['worker_id'],
            'task_ids' => array_values(array_map('intval', $payload['task_ids'])),
        ];
    }

    /**
     * @param  list<int>  $taskIds
     */
    public static function store(int $teamId, int $workerId, array $taskIds): void
    {
        session([
            self::sessionKey($teamId) => [
                'worker_id' => $workerId,
                'task_ids' => array_values(array_unique(array_map('intval', $taskIds))),
            ],
        ]);
    }
}
