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

    case A6Print = 'a6_print';

    case A5Print = 'a5_print';

    case A4Print = 'a4_print';

    public function fileSlug(): string
    {
        return match ($this) {
            self::Avery55x55S => '55x55-S',
            self::Herma7050 => '70x50-herma',
            self::Avery62x89R => '62x89-R',
            self::A6Print => 'A6',
            self::A5Print => 'A5',
            self::A4Print => 'A4',
        };
    }

    public function labelsPerPage(): int
    {
        return match ($this) {
            self::Avery55x55S => 15,
            self::Herma7050 => 15,
            self::Avery62x89R => 9,
            self::A6Print, self::A5Print, self::A4Print => 1,
        };
    }

    public function stickerWidthMm(): float
    {
        return match ($this) {
            self::Avery55x55S => 55.0,
            self::Herma7050 => 70.0,
            self::Avery62x89R => 62.0,
            self::A6Print, self::A5Print, self::A4Print => $this->pageWidthMm(),
        };
    }

    public function stickerHeightMm(): float
    {
        return match ($this) {
            self::Avery55x55S => 55.0,
            self::Herma7050 => 50.0,
            self::Avery62x89R => 89.0,
            self::A6Print, self::A5Print, self::A4Print => $this->pageHeightMm(),
        };
    }

    public function pageWidthMm(): float
    {
        return match ($this) {
            self::A6Print => 105.0,
            self::A5Print => 148.0,
            self::A4Print => 210.0,
            default => throw new \RuntimeException('Page size is only defined for printable page templates.'),
        };
    }

    public function pageHeightMm(): float
    {
        return match ($this) {
            self::A6Print => 148.0,
            self::A5Print => 210.0,
            self::A4Print => 297.0,
            default => throw new \RuntimeException('Page size is only defined for printable page templates.'),
        };
    }

    public function isPrintablePage(): bool
    {
        return match ($this) {
            self::A6Print, self::A5Print, self::A4Print => true,
            default => false,
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
