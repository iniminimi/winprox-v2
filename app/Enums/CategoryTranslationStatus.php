<?php

namespace App\Enums;

enum CategoryTranslationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
