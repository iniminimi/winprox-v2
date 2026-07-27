<?php

declare(strict_types=1);

namespace App\Enums;

enum IotEventStatus: string
{
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Deduped = 'deduped';
    case Failed = 'failed';
}
