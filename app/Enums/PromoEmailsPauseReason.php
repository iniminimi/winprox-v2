<?php

declare(strict_types=1);

namespace App\Enums;

enum PromoEmailsPauseReason: string
{
    case Manual = 'manual';
    case DomainBlock = 'domain_block';
    case Schedule = 'schedule';
    case Cli = 'cli';

    public function labelKey(): string
    {
        return 'platform.promo_campaigns.paused_reason_'.$this->value;
    }

    public static function tryFromMixed(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom($value);
    }
}
