<?php

namespace App\Enums;

enum ClockDisplayClaimStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Denied = 'denied';
    case Expired = 'expired';
}
