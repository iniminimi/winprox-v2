<?php

namespace App\Enums;

enum IssueTranslationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
