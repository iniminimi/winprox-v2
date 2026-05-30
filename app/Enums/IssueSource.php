<?php

namespace App\Enums;

enum IssueSource: string
{
    case Manager = 'manager';
    case Qr = 'qr';
    case QrLocation = 'qr_location';

    public function labelKey(): string
    {
        return match ($this) {
            self::Manager => 'issues.card.source_manual',
            self::Qr => 'issues.card.source_qr',
            self::QrLocation => 'issues.card.source_qr_location',
        };
    }
}
