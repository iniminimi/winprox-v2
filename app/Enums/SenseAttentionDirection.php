<?php

declare(strict_types=1);

namespace App\Enums;

enum SenseAttentionDirection: string
{
    case Below = 'below';
    case Above = 'above';
    case Stale = 'stale';
    case Missing = 'missing';
}
