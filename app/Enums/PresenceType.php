<?php

namespace App\Enums;

enum PresenceType: string
{
    case In = 'IN';
    case Out = 'OUT';

    /** RSZ OpenAPI enum: "in" | "out" (lowercase). */
    public function rszApiValue(): string
    {
        return match ($this) {
            self::In => 'in',
            self::Out => 'out',
        };
    }
}
