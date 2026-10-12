<?php

namespace App\Enums;

enum HolidayAgreementEffect: string
{
    case None = 'none';
    case Dropped = 'dropped';
    case Replaced = 'replaced';
}
