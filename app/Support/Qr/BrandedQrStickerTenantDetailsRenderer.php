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
        $layout = self::layout($lines, $logoPlacement);
        if ($layout === null) {
            return;
        }

        BrandedQrStickerSurfaceFrame::drawOnGd(
            $canvas,
            $layout['box_x'],
            $layout['box_y'],
            $layout['box_width'],
            $layout['box_height'],
        );

        [$r, $g, $b] = BrandedQrStickerTextColor::darkLabelRgb();
        $color = imagecolorallocate($canvas, $r, $g, $b);
        if ($color === false) {
            throw new \RuntimeException('Unable to allocate branded sticker tenant details color.');
        }

        $x = $layout['text_x'];
        $y = $layout['text_y'];

        foreach ($layout['lines'] as $line) {
            $bbox = imagettfbbox($layout['font_size'], 0, $layout['font'], $line);
            if (! is_array($bbox)) {
                throw new \RuntimeException('Unable to measure branded sticker tenant details.');
            }

            $ascent = abs($bbox[7]);
            imagettftext($canvas, $layout['font_size'], 0, $x, $y + $ascent, $color, $layout['font'], $line);
            $y += $layout['line_height'];
        }
    }

    /**
     * @param  list<string>  $lines
     */
    public static function drawOnImagick(Imagick $canvas, array $lines, QrStickerTenantLogoPlacement $logoPlacement): void
    {
        $layout = self::layout($lines, $logoPlacement);
        if ($layout === null) {
            return;
        }

        BrandedQrStickerSurfaceFrame::drawOnImagick(
            $canvas,
            $layout['box_x'],
            $layout['box_y'],
            $layout['box_width'],
            $layout['box_height'],
        );

        [$r, $g, $b] = BrandedQrStickerTextColor::darkLabelRgb();

        $draw = new ImagickDraw;
        $draw->setFont($layout['font']);
        $draw->setFontSize((float) $layout['font_size']);
        $draw->setFillColor(new ImagickPixel(sprintf('rgb(%d,%d,%d)', $r, $g, $b)));
        $draw->setTextAlignment(Imagick::ALIGN_LEFT);

        $x = (float) $layout['text_x'];
        $y = (float) $layout['text_y'];

        foreach ($layout['lines'] as $line) {
            $metrics = $canvas->queryFontMetrics($draw, $line);
            $ascent = is_array($metrics) ? (float) ($metrics['ascender'] ?? $layout['font_size']) : (float) $layout['font_size'];
            $canvas->annotateImage($draw, $x, $y + $ascent, 0, $line);
            $y += $layout['line_height'];
        }
    }

    /**
     * @param  list<string>  $lines
     * @return array{
     *     lines: list<string>,
     *     font: string,
     *     font_size: int,
     *     line_height: int,
     *     text_x: int,
     *     text_y: int,
     *     box_x: int,
     *     box_y: int,
     *     box_width: int,
     *     box_height: int
     * }|null
     */
    private static function layout(array $lines, QrStickerTenantLogoPlacement $logoPlacement): ?array
    {
        $lines = self::normalizedLines($lines);
        if ($lines === []) {
            return null;
        }

        $font = BrandedQrStickerFont::headerBoldAbsolutePath();
        $maxTextWidth = Avery62x89StickerArtworkLayout::tenantDetailsMaxWidthPx($logoPlacement)
            - BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $fontSize = self::fitFontSize($lines, $font, max(1, $maxTextWidth));
        $lineHeight = (int) round($fontSize * Avery62x89StickerArtworkLayout::TENANT_DETAILS_LINE_HEIGHT_RATIO);
        $textWidth = self::maxLineWidth($lines, $font, $fontSize);
        $textHeight = $lineHeight * count($lines);

        $boxWidth = $textWidth + BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $boxHeight = $textHeight + BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        $boxX = Avery62x89StickerArtworkLayout::TENANT_DETAILS_PADDING_LEFT_PX;
        $boxY = Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX
            - Avery62x89StickerArtworkLayout::TENANT_DETAILS_PADDING_BOTTOM_PX
            - $boxHeight;

        return [
            'lines' => $lines,
            'font' => $font,
            'font_size' => $fontSize,
            'line_height' => $lineHeight,
            'text_x' => $boxX + BrandedQrStickerSurfaceFrame::contentInsetPx(),
            'text_y' => $boxY + BrandedQrStickerSurfaceFrame::contentInsetPx(),
            'box_x' => $boxX,
            'box_y' => $boxY,
            'box_width' => $boxWidth,
            'box_height' => $boxHeight,
        ];
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

    /**
     * @param  list<string>  $lines
     */
    private static function maxLineWidth(array $lines, string $fontPath, int $fontSize): int
    {
        $max = 0;
        foreach ($lines as $line) {
            $max = max($max, self::textWidth($fontPath, $fontSize, $line));
        }

        return $max;
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
