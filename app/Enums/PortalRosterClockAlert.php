<?php

namespace App\Enums;

enum PortalRosterClockAlert: string
{
    case Absent = 'absent';
    case Early = 'early';
    case Late = 'late';
    case LateOut = 'late_out';
    case AbsenceLeave = 'absence_leave';
    case AbsenceRecup = 'absence_recup';
    case AbsenceSick = 'absence_sick';
    case AbsenceHoliday = 'absence_holiday';
}
