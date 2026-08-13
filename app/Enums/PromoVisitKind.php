<?php

namespace App\Enums;

enum PromoVisitKind: string
{
    case Hit = 'hit';
    case Engaged = 'engaged';
    case Follow = 'follow';
}
