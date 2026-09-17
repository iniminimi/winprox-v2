<?php

namespace App\Enums;

enum FieldSyncType: string
{
    case TaskStart = 'task.start';
    case TaskComplete = 'task.complete';
    case IssueCreate = 'issue.create';
    case UnitCheck = 'unit.check';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
