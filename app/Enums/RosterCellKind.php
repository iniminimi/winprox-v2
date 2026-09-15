<?php

namespace App\Enums;

enum RosterCellKind: string
{
    case Empty = 'empty';
    case ShiftType = 'shift_type';
    case FreeTime = 'free_time';
    case Invalid = 'invalid';

    public function isSavable(): bool
    {
        return $this === self::Empty
            || $this === self::ShiftType
            || $this === self::FreeTime;
    }
}
