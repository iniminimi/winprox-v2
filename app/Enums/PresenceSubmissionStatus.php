<?php

namespace App\Enums;

enum PresenceSubmissionStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
