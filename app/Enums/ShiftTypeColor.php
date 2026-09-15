<?php

namespace App\Enums;

enum ShiftTypeColor: string
{
    case Emerald = 'emerald';
    case Amber = 'amber';
    case Slate = 'slate';
    case Sky = 'sky';

    public static function default(): self
    {
        return self::Slate;
    }

    public static function freeTime(): self
    {
        return self::Slate;
    }
}
