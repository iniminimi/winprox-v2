<?php

declare(strict_types=1);

namespace App\Enums;

enum HardwareDeviceCondition: string
{
    case New = 'new';
    case Refurbished = 'refurbished';
    case Faulty = 'faulty';

    public function labelKey(): string
    {
        return 'ops.device_condition.'.$this->value;
    }
}
