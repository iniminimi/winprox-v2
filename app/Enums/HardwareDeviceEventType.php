<?php

declare(strict_types=1);

namespace App\Enums;

enum HardwareDeviceEventType: string
{
    case Received = 'received';
    case MacLinked = 'mac_linked';
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';
    case Installed = 'installed';
    case Uninstalled = 'uninstalled';
    case Returned = 'returned';
    case Retired = 'retired';
    case Lost = 'lost';
    case Updated = 'updated';
}
