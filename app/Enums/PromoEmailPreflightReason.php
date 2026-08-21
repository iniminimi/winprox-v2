<?php

declare(strict_types=1);

namespace App\Enums;

enum PromoEmailPreflightReason: string
{
    case InvalidSyntax = 'invalid_syntax';
    case NoMx = 'no_mx';
    case Unsubscribed = 'unsubscribed';
    case PreviouslyBounced = 'previously_bounced';

    public function labelKey(): string
    {
        return 'platform.promo_campaigns.email_skip_reason.'.$this->value;
    }
}
