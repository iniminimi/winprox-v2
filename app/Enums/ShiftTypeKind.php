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

    /** Clock Point absence picker: Verlof → Recup → Ziek → Andere. */
    public function requestableListOrder(): int
    {
        return match ($this) {
            self::Leave => 0,
            self::Recup => 1,
            self::Sick => 2,
            self::Other => 3,
            self::Work => 9,
        };
    }

    /** Andere (KV, baaldag, …) is always a single calendar day. */
    public function limitsAbsenceToSingleDay(): bool
    {
        return $this === self::Other;
    }

    /** Sick and other never feed the replacement pool. */
    public function allowsReplacementPool(): bool
    {
        return $this !== self::Sick && $this !== self::Other;
    }
}
