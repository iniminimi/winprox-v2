<?php

namespace App\Enums;

enum ClockDeviceRefusalReason: string
{
    case Foreign = 'clock_device_foreign';
    case Missing = 'clock_device_missing';
    case Mismatch = 'clock_device_mismatch';
}
