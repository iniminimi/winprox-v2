<?php

namespace App\Enums;

enum PromoVisitPage: string
{
    case Promo = 'promo';
    case Welcome = 'welcome';
    case Hospitality = 'hospitality';
    case Industry = 'industry';
    case Healthcare = 'healthcare';
    case Government = 'government';

    public function labelKey(): string
    {
        return match ($this) {
            self::Promo => 'platform.promo_recipients.page_promo',
            self::Welcome => 'platform.promo_recipients.page_welcome',
            self::Hospitality => 'platform.promo_recipients.page_hospitality',
            self::Industry => 'platform.promo_recipients.page_industry',
            self::Healthcare => 'platform.promo_recipients.page_healthcare',
            self::Government => 'platform.promo_recipients.page_government',
        };
    }

    public function isLandingHit(): bool
    {
        return $this !== self::Welcome;
    }
}
