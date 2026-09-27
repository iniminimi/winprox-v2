<?php

namespace App\Enums;

enum ClockDisplayClaimDenyReason: string
{
    case Admin = 'admin';
    case CodeReissued = 'code_reissued';
}
