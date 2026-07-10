<?php

namespace App\Enums;

enum ClockSource: string
{
    case ClockPointQr = 'clock_point_qr';
    case Admin = 'admin';
    case Auto = 'auto';
    case Api = 'api';
}
