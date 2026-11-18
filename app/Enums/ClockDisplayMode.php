<?php

namespace App\Enums;

enum ClockDisplayMode: string
{
    case Online = 'online';
    case Ble = 'ble';

    public function isBle(): bool
    {
        return $this === self::Ble;
    }
}
