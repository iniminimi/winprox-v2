<?php

namespace App\Enums;

enum ShiftTypeKind: string
{
    case Work = 'work';
    case Leave = 'leave';
    case Recup = 'recup';
    case Sick = 'sick';

    public function isWork(): bool
    {
        return $this === self::Work;
    }

    public function isAbsence(): bool
    {
        return $this !== self::Work;
    }

    public function isRequestableAbsence(): bool
    {
        return $this === self::Leave || $this === self::Recup;
    }
}
