<?php

namespace App\Enums;

/**
 * Expliciete prik vanaf het klokscherm (na PIN). Zonder action blijft
 * pin-clock de oude toggle in/uit voor oudere firmware.
 */
enum ClockDisplayPinAction: string
{
    case In = 'in';
    case Out = 'out';
    case BreakStart = 'break_start';
    case BreakEnd = 'break_end';
}
