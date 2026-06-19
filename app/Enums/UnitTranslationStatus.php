<?php

namespace App\Enums;

enum UnitTranslationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
