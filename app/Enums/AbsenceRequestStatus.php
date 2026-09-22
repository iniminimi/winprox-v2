<?php

namespace App\Enums;

enum AbsenceRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isPending(): bool
    {
        return $this === self::Pending;
    }
}
