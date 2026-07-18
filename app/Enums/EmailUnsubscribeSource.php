<?php

declare(strict_types=1);

namespace App\Enums;

enum EmailUnsubscribeSource: string
{
    case Voluntary = 'voluntary';
    case Undeliverable = 'undeliverable';
    case Manual = 'manual';

    public function labelKey(): string
    {
        return match ($this) {
            self::Voluntary => 'platform.email_unsubscribe.source_voluntary',
            self::Undeliverable => 'platform.email_unsubscribe.source_undeliverable',
            self::Manual => 'platform.email_unsubscribe.source_manual',
        };
    }
}
