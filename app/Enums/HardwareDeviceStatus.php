<?php

declare(strict_types=1);

namespace App\Enums;

enum HardwareDeviceStatus: string
{
    case InStock = 'in_stock';
    case Assigned = 'assigned';
    case InUse = 'in_use';
    case Returned = 'returned';
    case Retired = 'retired';
    case Lost = 'lost';

    public function labelKey(): string
    {
        return 'ops.device_status.'.$this->value;
    }
}
