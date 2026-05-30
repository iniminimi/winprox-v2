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

    public function averySku(): string
    {
        return match ($this) {
            self::Avery55x55S => '55x55-S',
        };
    }

    public function labelsPerPage(): int
    {
        return match ($this) {
            self::Avery55x55S => 15,
        };
    }

    public function stickerWidthMm(): float
    {
        return match ($this) {
            self::Avery55x55S => 55.0,
        };
    }
}
