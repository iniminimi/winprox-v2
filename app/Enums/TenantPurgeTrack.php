<?php

namespace App\Enums;

enum TenantPurgeTrack: string
{
    case Trial = 'trial';
    case Paid = 'paid';
}
