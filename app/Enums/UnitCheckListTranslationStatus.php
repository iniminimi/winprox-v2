<?php

namespace App\Enums;

enum UnitCheckListTranslationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
