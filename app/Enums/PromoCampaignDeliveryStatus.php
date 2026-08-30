<?php

declare(strict_types=1);

namespace App\Enums;

enum PromoCampaignDeliveryStatus: string
{
    case NoRecipients = 'no_recipients';
    case Complete = 'complete';
    case Paused = 'paused';
    case Sending = 'sending';
    case NeedsRestart = 'needs_restart';
    case NotStarted = 'not_started';

    public function labelKey(): string
    {
        return 'platform.promo_campaigns.delivery_status.'.$this->value;
    }

    public function pillClass(): string
    {
        return match ($this) {
            self::Complete => 'wp-pill wp-pill--done',
            self::Sending => 'wp-pill wp-pill--progress',
            self::Paused, self::NeedsRestart => 'wp-pill wp-pill--new',
            self::NotStarted, self::NoRecipients => 'wp-pill wp-pill--closed',
        };
    }
}
