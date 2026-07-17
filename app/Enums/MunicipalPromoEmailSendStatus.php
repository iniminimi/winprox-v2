<?php

declare(strict_types=1);

namespace App\Enums;

enum MunicipalPromoEmailSendStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Bounced = 'bounced';
}
