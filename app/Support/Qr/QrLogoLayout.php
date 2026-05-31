<?php

declare(strict_types=1);

namespace App\Support\Qr;

/**
 * Afmetingen centrelogo in QR-codes (ErrorCorrectionLevel::H, ~30% herstel).
 * Box-ratio ~30% is het praktische maximum voor betrouwbare scans.
 */
final class QrLogoLayout
{
    /** Volledige QR-weergave (scherm + A4/A6-print). */
    public const DISPLAY_BOX_RATIO = 0.30;

    /** Avery 55×55 mm stickers — iets kleiner i.v.m. fysieke modulegrootte. */
    public const STICKER_BOX_RATIO = 0.27;

    /** Binnenpadding wit kader — minimaal, logo vult het kader. */
    public const BOX_INNER_PADDING_RATIO = 0.02;

    /** Vierkant WinProx-icoon (zonder A6-tekst). */
    public const WINPROX_QR_CENTER_SVG = 'images/qr/svg/winprox_qr_center.svg';

    public static function innerPaddingPx(): int
    {
        return 2;
    }

    public static function displayBoxPercent(): int
    {
        return (int) round(self::DISPLAY_BOX_RATIO * 100);
    }

    public static function innerPaddingPercent(): int
    {
        return (int) round(self::BOX_INNER_PADDING_RATIO * 100);
    }
}
