<?php

namespace App\Enums;

enum InternalTeamTranslationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
