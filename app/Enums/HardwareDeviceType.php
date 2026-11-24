<?php

declare(strict_types=1);

namespace App\Enums;

enum HardwareDeviceType: string
{
    case ClockDisplay = 'clock_display';

    public function labelKey(): string
    {
        return 'ops.device_type.'.$this->value;
    }
}
