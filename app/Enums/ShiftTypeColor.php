<?php

namespace App\Enums;

enum ShiftTypeColor: string
{
    case None = 'none';
    case Green = 'green';
    case Petrol = 'petrol';
    case Blue = 'blue';
    case Purple = 'purple';
    case Pink = 'pink';
    case Orange = 'orange';
    case Yellow = 'yellow';
    case Gray = 'gray';

    public static function default(): self
    {
        return self::None;
    }

    public static function freeTime(): self
    {
        return self::None;
    }

    public function hasFill(): bool
    {
        return $this !== self::None;
    }
}
