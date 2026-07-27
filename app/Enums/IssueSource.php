<?php

namespace App\Enums;

enum IssueSource: string
{
    case Manager = 'manager';
    case Qr = 'qr';
    case Iot = 'iot';

    public function labelKey(): string
    {
        return match ($this) {
            self::Manager => 'issues.card.source_manual',
            self::Qr => 'issues.card.source_qr',
            self::Iot => 'issues.card.source_iot',
        };
    }
}
