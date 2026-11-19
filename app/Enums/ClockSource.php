<?php

namespace App\Enums;

enum ClockSource: string
{
    case ClockPointQr = 'clock_point_qr';
    /** Legacy BLE-prikken (nov 2026); alleen nog in oude rijen / tot migratie. */
    case Bluetooth = 'bluetooth';
    case ClockDisplayPin = 'clock_display_pin';
    case Backoffice = 'backoffice';
    case Admin = 'admin';
    case Auto = 'auto';
    case Api = 'api';
    case Gps = 'gps';
}
