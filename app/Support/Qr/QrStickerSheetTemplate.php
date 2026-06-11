<?php

declare(strict_types=1);

namespace App\Support\Qr;

/**
 * Supported physical sticker sheet layouts for QR pack Word export.
 * Add new cases when additional Avery (or other) templates are offered.
 */
enum QrStickerSheetTemplate: string
{
    case Avery55x55S = 'avery_55x55_s';

    case Herma7050 = 'herma_70x50';

    case Avery62x89R = 'avery_62x89_r';

    public function fileSlug(): string
    {
        return match ($this) {
            self::Avery55x55S => '55x55-S',
            self::Herma7050 => '70x50-herma',
            self::Avery62x89R => '62x89-R',
        };
    }

    public function labelsPerPage(): int
    {
        return match ($this) {
            self::Avery55x55S => 15,
            self::Herma7050 => 15,
            self::Avery62x89R => 9,
        };
    }

    public function stickerWidthMm(): float
    {
        return match ($this) {
            self::Avery55x55S => 55.0,
            self::Herma7050 => 70.0,
            self::Avery62x89R => 62.0,
        };
    }

    public function stickerHeightMm(): float
    {
        return match ($this) {
            self::Avery55x55S => 55.0,
            self::Herma7050 => 50.0,
            self::Avery62x89R => 89.0,
        };
    }

    public function isBranded(): bool
    {
        return match ($this) {
            self::Avery62x89R => true,
            default => false,
        };
    }
}
