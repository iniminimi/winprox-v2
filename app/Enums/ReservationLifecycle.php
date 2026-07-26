<?php

namespace App\Enums;

enum ReservationLifecycle: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * Maps onto the shared `.wp-pill` variants (no bespoke reservation pill styles).
     */
    public function pillVariant(): string
    {
        return match ($this) {
            self::Pending => 'new',
            self::Confirmed => 'progress',
            self::Cancelled, self::Expired => 'closed',
        };
    }
}
