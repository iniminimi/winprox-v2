<?php

namespace App\Enums;

enum TimePresenceAttentionType: string
{
    case LongShift = 'long_shift';
    case StaleShift = 'stale_shift';
    case NoBreak = 'no_break';
    case RapidHop = 'rapid_hop';
}
