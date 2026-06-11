<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrStickerTenantLogoPlacement;
use Imagick;
use ImagickDraw;
use ImagickPixel;

/** Tenant name + address, bottom-left on Avery 62×89-R branded artwork. */
final class BrandedQrStickerTenantDetailsRenderer
{
    /**
     * @param  list<string>  $lines
     * @param  \GdImage|resource  $canvas
     */
    public static function drawOnGd($canvas, array $lines, QrStickerTenantLogoPlacement $logoPlacement): void
    {
        $lines = self::normalizedLines($lines);
        if ($lines === []) {
            return;
        }

        $font = BrandedQrStickerFont::headerBoldAbsolutePath();
        $maxWidth = Avery62x89StickerArtworkLayout::tenantDetailsMaxWidthPx($logoPlacement);
        $fontSize = self::fitFontSize($lines, $font, $maxWidth);
        $lineHeight = (int) round($fontSize * Avery62x89StickerArtworkLayout::TENANT_DETAILS_LINE_HEIGHT_RATIO);

        [$r, $g, $b] = BrandedQrStickerTextColor::darkLabelRgb();
        $color = imagecolorallocate($canvas, $r, $g, $b);
        if ($color === false) {
            throw new \RuntimeException('Unable to allocate branded sticker tenant details color.');
        }

        $x = Avery62x89StickerArtworkLayout::TENANT_DETAILS_PADDING_LEFT_PX;
        $blockBottom = Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX
            - Avery62x89StickerArtworkLayout::TENANT_DETAILS_PADDING_BOTTOM_PX;
        $blockTop = $blockBottom - ($lineHeight * count($lines));
        $y = $blockTop;

        foreach ($lines as $line) {
            $bbox = imagettfbbox($fontSize, 0, $font, $line);
            if (! is_array($bbox)) {
                throw new \RuntimeException('Unable to measure branded sticker tenant details.');
            }

            $ascent = abs($bbox[7]);
            imagettftext($canvas, $fontSize, 0, $x, $y + $ascent, $color, $font, $line);
            $y += $lineHeight;
        }
    }

    /**
     * @param  list<string>  $lines
     */
    public static function drawOnImagick(Imagick $canvas, array $lines, QrStickerTenantLogoPlacement $logoPlacement): void
    {
        $lines = self::normalizedLines($lines);
        if ($lines === []) {
            return;
        }

        $font = BrandedQrStickerFont::headerBoldAbsolutePath();
        $maxWidth = Avery62x89StickerArtworkLayout::tenantDetailsMaxWidthPx($logoPlacement);
        $fontSize = self::fitFontSize($lines, $font, $maxWidth);
        $lineHeight = $fontSize * Avery62x89StickerArtworkLayout::TENANT_DETAILS_LINE_HEIGHT_RATIO;

        [$r, $g, $b] = BrandedQrStickerTextColor::darkLabelRgb();

        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize((float) $fontSize);
        $draw->setFillColor(new ImagickPixel(sprintf('rgb(%d,%d,%d)', $r, $g, $b)));
        $draw->setTextAlignment(Imagick::ALIGN_LEFT);

        $x = (float) Avery62x89StickerArtworkLayout::TENANT_DETAILS_PADDING_LEFT_PX;
        $blockBottom = (float) Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX
            - Avery62x89StickerArtworkLayout::TENANT_DETAILS_PADDING_BOTTOM_PX;
        $blockTop = $blockBottom - ($lineHeight * count($lines));
        $y = $blockTop;

        foreach ($lines as $line) {
            $metrics = $canvas->queryFontMetrics($draw, $line);
            $ascent = is_array($metrics) ? (float) ($metrics['ascender'] ?? $fontSize) : (float) $fontSize;
            $canvas->annotateImage($draw, $x, $y + $ascent, 0, $line);
            $y += $lineHeight;
        }
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private static function normalizedLines(array $lines): array
    {
        $normalized = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $normalized[] = $line;
            }
        }

        return array_slice($normalized, 0, Avery62x89StickerArtworkLayout::TENANT_DETAILS_MAX_LINES);
    }

    /**
     * @param  list<string>  $lines
     */
    private static function fitFontSize(array $lines, string $fontPath, int $maxWidth): int
    {
        for ($fontSize = Avery62x89StickerArtworkLayout::TENANT_DETAILS_MAX_FONT_SIZE_PX;
            $fontSize >= Avery62x89StickerArtworkLayout::TENANT_DETAILS_MIN_FONT_SIZE_PX;
            $fontSize--) {
            $fits = true;
            foreach ($lines as $line) {
                if (self::textWidth($fontPath, $fontSize, $line) > $maxWidth) {
                    $fits = false;
                    break;
                }
            }

            $lineHeight = $fontSize * Avery62x89StickerArtworkLayout::TENANT_DETAILS_LINE_HEIGHT_RATIO;
            if ($fits && ($lineHeight * count($lines)) <= Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_HEIGHT_PX + 24) {
                return $fontSize;
            }
        }

        return Avery62x89StickerArtworkLayout::TENANT_DETAILS_MIN_FONT_SIZE_PX;
    }

    private static function textWidth(string $fontPath, int $fontSize, string $text): int
    {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        if (! is_array($bbox)) {
            throw new \RuntimeException('Unable to measure branded sticker tenant details width.');
        }

        return (int) abs($bbox[2] - $bbox[0]);
    }
}
