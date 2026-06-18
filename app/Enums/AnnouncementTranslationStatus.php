<?php

namespace App\Enums;

enum AnnouncementTranslationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
