<?php

namespace App\Enums;

enum ShiftTypeKind: string
{
    case Work = 'work';
    case Leave = 'leave';
    case Recup = 'recup';
    case Sick = 'sick';
    /** Tenant-labelled absences (klein verlet, omstandigheid, …) — not leave balance. */
    case Other = 'other';

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
        return $this === self::Leave
            || $this === self::Recup
            || $this === self::Sick
            || $this === self::Other;
    }

    /** Sick and other never feed the replacement pool. */
    public function allowsReplacementPool(): bool
    {
        return $this !== self::Sick && $this !== self::Other;
    }
}
