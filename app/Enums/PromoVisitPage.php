<?php

namespace App\Enums;

enum PromoVisitPage: string
{
    case Promo = 'promo';
    case Welcome = 'welcome';

    public function labelKey(): string
    {
        return match ($this) {
            self::Promo => 'platform.promo_recipients.page_promo',
            self::Welcome => 'platform.promo_recipients.page_welcome',
        };
    }
}
