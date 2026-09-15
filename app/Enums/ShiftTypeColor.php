<?php

namespace App\Enums;

enum ShiftTypeColor: string
{
    case None = 'none';
    case Emerald = 'emerald';
    case Mint = 'mint';
    case Teal = 'teal';
    case Sky = 'sky';
    case Ice = 'ice';
    case Navy = 'navy';
    case Amber = 'amber';
    case Gold = 'gold';
    case Sand = 'sand';
    case Rose = 'rose';
    case Slate = 'slate';

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
