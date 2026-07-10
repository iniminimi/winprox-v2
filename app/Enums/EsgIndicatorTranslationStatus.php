<?php

namespace App\Enums;

enum EsgIndicatorTranslationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
