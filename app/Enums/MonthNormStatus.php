<?php

namespace App\Enums;

enum MonthNormStatus: string
{
    case Available = 'available';
    case Incomplete = 'incomplete';
    case Conflict = 'conflict';
}
