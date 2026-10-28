<?php

namespace App\Enums;

enum RosterRestorePointSource: string
{
    case Grid = 'grid';
    case Day = 'day';
    case Copy = 'copy';
    case Restore = 'restore';
}
