<?php

namespace App\Enums;

enum WorkerIdentityStatus: string
{
    case Found = 'found';
    case Claimable = 'claimable';
    case NotFound = 'not_found';
    case Ambiguous = 'ambiguous';
}
