<?php

namespace App\Enums;

enum WorkShiftStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case ForceClosed = 'force_closed';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
