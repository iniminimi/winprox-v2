<?php

namespace App\Support;

use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;

class EntityDetailNavigation
{
    /**
     * @return array{firstId: ?int, prevId: ?int, nextId: ?int, lastId: ?int}
     */
    public static function forLocation(Location $location): array
    {
        $ids = Location::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->pluck('id')
            ->all();

        return self::resolve($ids, $location->id);
    }

    public static function forIssue(Issue $issue): array
    {
        $ids = Issue::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        return self::resolve($ids, $issue->id);
    }

    /**
     * @return array{firstId: ?int, prevId: ?int, nextId: ?int, lastId: ?int}
     */
    public static function forTask(Task $task): array
    {
        $ids = Task::query()
            ->forApprovedIssue()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->all();

        return self::resolve($ids, $task->id);
    }

    /**
     * @param  list<int>  $ids
     * @return array{firstId: ?int, prevId: ?int, nextId: ?int, lastId: ?int}
     */
    private static function resolve(array $ids, int $currentId): array
    {
        $index = array_search($currentId, $ids, true);
        $count = count($ids);

        return [
            'firstId' => $count > 0 ? $ids[0] : null,
            'prevId' => ($index !== false && $index > 0) ? $ids[$index - 1] : null,
            'nextId' => ($index !== false && $index < $count - 1) ? $ids[$index + 1] : null,
            'lastId' => $count > 0 ? $ids[$count - 1] : null,
        ];
    }
}
