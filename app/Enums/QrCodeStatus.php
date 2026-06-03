<?php

namespace App\Enums;

enum QrCodeStatus: string
{
    case Unassigned = 'unassigned';
    case Active = 'active';
    case Damaged = 'damaged';
    case Inactive = 'inactive';

    /** Vertaalsleutel (lang/[locale]/common.json -> common.qr_status.*). */
    public function labelKey(): string
    {
        return 'common.qr_status.'.$this->value;
    }

    /** CSS-pilvariant (.wp-pill--*). */
    public function pillModifier(): string
    {
        return match ($this) {
            self::Unassigned => 'neutral',
            self::Active => 'success',
            self::Damaged => 'warning',
            self::Inactive => 'danger',
        };
    }

    /** Kan gekoppeld worden aan een Unit? */
    public function canLink(): bool
    {
        return $this === self::Unassigned;
    }

    /** Kan gescand worden voor meldingen? */
    public function canScan(): bool
    {
        return $this === self::Active;
    }
}
