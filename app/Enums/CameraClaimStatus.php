<?php

declare(strict_types=1);

namespace App\Enums;

enum CameraClaimStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Denied = 'denied';
}
