<?php

namespace App\Enums;

enum PortalRosterClockAlert: string
{
    case Absent = 'absent';
    case Early = 'early';
    case Late = 'late';
    case LateOut = 'late_out';
}
