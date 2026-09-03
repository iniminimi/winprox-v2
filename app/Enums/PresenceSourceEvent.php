<?php

namespace App\Enums;

enum PresenceSourceEvent: string
{
    case ClockIn = 'clock_in';
    case ClockOut = 'clock_out';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
}
