<?php

declare(strict_types=1);

namespace App\Support\Qr;

use Imagick;
use ImagickDraw;
use ImagickPixel;

/** Unit line (locatie · unit) directly below the QR when tenant header text is set. */
final class BrandedQrStickerUnitCaptionRenderer
{
    /**
     * @param  \GdImage|resource  $canvas
     */
    public static function drawOnGd($canvas, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $font = BrandedQrStickerFont::headerBoldAbsolutePath();
        $fontSize = self::fitFontSize($text, $font);
        $textWidth = self::textWidth($font, $fontSize, $text);

        [$r, $g, $b] = BrandedQrStickerTextColor::rgbForGdRegion(
            $canvas,
            Avery62x89StickerArtworkLayout::QR_CENTER_X_PX,
            BrandedQrStickerTextColor::unitCaptionSampleCenterY(),
            Avery62x89StickerArtworkLayout::footerMaxWidthPx(),
            36,
        );
        $color = imagecolorallocate($canvas, $r, $g, $b);
        if ($color === false) {
            throw new \RuntimeException('Unable to allocate branded sticker unit caption color.');
        }

        $x = (int) round((Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX - $textWidth) / 2);
        $bbox = imagettfbbox($fontSize, 0, $font, $text);
        if (! is_array($bbox)) {
            throw new \RuntimeException('Unable to measure branded sticker unit caption.');
        }

        $ascent = abs($bbox[7]);
        $y = Avery62x89StickerArtworkLayout::UNIT_CAPTION_TOP_PX + $ascent;

        imagettftext($canvas, $fontSize, 0, max(0, $x), $y, $color, $font, $text);
    }

    public static function drawOnImagick(Imagick $canvas, string $text): void
    {
        $text = trim($text);
        if ($text === '') {
            return;
        }

        $font = BrandedQrStickerFont::headerBoldAbsolutePath();
        $fontSize = self::fitFontSize($text, $font);

        [$r, $g, $b] = BrandedQrStickerTextColor::rgbForImagickRegion(
            $canvas,
            Avery62x89StickerArtworkLayout::QR_CENTER_X_PX,
            BrandedQrStickerTextColor::unitCaptionSampleCenterY(),
            Avery62x89StickerArtworkLayout::footerMaxWidthPx(),
            36,
        );

        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize((float) $fontSize);
        $draw->setFillColor(new ImagickPixel(sprintf('rgb(%d,%d,%d)', $r, $g, $b)));
        $draw->setTextAlignment(Imagick::ALIGN_CENTER);

        $metrics = $canvas->queryFontMetrics($draw, $text);
        $ascent = is_array($metrics) ? (float) ($metrics['ascender'] ?? $fontSize) : (float) $fontSize;
        $centerX = Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX / 2;
        $y = (float) Avery62x89StickerArtworkLayout::UNIT_CAPTION_TOP_PX + $ascent;

        $canvas->annotateImage($draw, $centerX, $y, 0, $text);
    }

    private static function fitFontSize(string $text, string $fontPath): int
    {
        $maxWidth = Avery62x89StickerArtworkLayout::footerMaxWidthPx();

        for ($fontSize = Avery62x89StickerArtworkLayout::UNIT_CAPTION_MAX_FONT_SIZE_PX;
            $fontSize >= Avery62x89StickerArtworkLayout::UNIT_CAPTION_MIN_FONT_SIZE_PX;
            $fontSize--) {
            if (self::textWidth($fontPath, $fontSize, $text) <= $maxWidth) {
                return $fontSize;
            }
        }

        return Avery62x89StickerArtworkLayout::UNIT_CAPTION_MIN_FONT_SIZE_PX;
    }

    private static function textWidth(string $fontPath, int $fontSize, string $text): int
    {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        if (! is_array($bbox)) {
            throw new \RuntimeException('Unable to measure branded sticker unit caption width.');
        }

        return (int) abs($bbox[2] - $bbox[0]);
    }
}
