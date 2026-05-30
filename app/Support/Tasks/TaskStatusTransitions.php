<?php

namespace App\Support\Tasks;

use App\Enums\TaskStatus;

/**
 * Toegestane statusovergangen voor facility-taken (4 statussen, §4.3 FEATURES.md).
 */
final class TaskStatusTransitions
{
    /**
     * @return array<string, list<TaskStatus>>
     */
    public static function allowed(): array
    {
        return [
            TaskStatus::New->value => [
                TaskStatus::InProgress,
                TaskStatus::Done,
                TaskStatus::Closed,
            ],
            TaskStatus::InProgress->value => [
                TaskStatus::Done,
                TaskStatus::Closed,
            ],
            TaskStatus::Done->value => [],
            TaskStatus::Closed->value => [
                TaskStatus::New,
            ],
        ];
    }

    public static function allows(TaskStatus $from, TaskStatus $to): bool
    {
        if ($from === $to) {
            return false;
        }

        $allowed = self::allowed()[$from->value] ?? [];

        return in_array($to, $allowed, true);
    }

    /** Sluiten zonder uitvoering: van Nieuw of In uitvoering → Gesloten. */
    public static function requiresReason(TaskStatus $from, TaskStatus $to): bool
    {
        return $to === TaskStatus::Closed
            && in_array($from, [TaskStatus::New, TaskStatus::InProgress], true);
    }

    /**
     * @return list<TaskStatus>
     */
    public static function nextOptions(TaskStatus $from): array
    {
        return self::allowed()[$from->value] ?? [];
    }
}
