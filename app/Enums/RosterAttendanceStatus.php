<?php

namespace App\Enums;

enum RosterAttendanceStatus: string
{
    case None = 'none';
    case Ok = 'ok';
    case Missing = 'missing';
    case Deviation = 'deviation';
    case Unplanned = 'unplanned';
    case Gap = 'gap';

    public function hasMarker(): bool
    {
        return $this === self::Missing
            || $this === self::Deviation
            || $this === self::Unplanned
            || $this === self::Gap;
    }
}
