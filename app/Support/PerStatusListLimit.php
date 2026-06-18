<?php

namespace App\Support;

final class PerStatusListLimit
{
    public const DEFAULT = 10;

    /** @var list<int> */
    public const OPTIONS = [10, 50, 100];

    public static function normalize(int $limit): int
    {
        return in_array($limit, self::OPTIONS, true) ? $limit : self::DEFAULT;
    }
}
