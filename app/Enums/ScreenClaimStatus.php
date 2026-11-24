<?php

declare(strict_types=1);

namespace App\Enums;

enum ScreenClaimStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Denied = 'denied';
}
