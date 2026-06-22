<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrStickerTenantLogoPlacement;
use GdImage;
use Imagick;
use ImagickDraw;
use ImagickPixel;

/** Full-width bottom white band — tenant address and/or corner logo (bottom placements only). */
final class BrandedQrStickerBottomBandRenderer
{
    /**
     * @param  list<string>  $lines
     * @param  GdImage|resource  $canvas
     */
    public static function drawOnGd(
        $canvas,
        array $lines,
        ?string $logoPath,
        QrStickerTenantLogoPlacement $logoPlacement,
    ): void {
        $layout = self::layout($lines, $logoPath, $logoPlacement);
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

        if ($layout['logo'] !== null) {
            self::drawLogoOnGd($canvas, $layout['logo']);
        }

        if ($layout['text'] !== null) {
            self::drawTextOnGd($canvas, $layout['text']);
        }
    }

    /**
     * @param  list<string>  $lines
     */
    public static function drawOnImagick(
        Imagick $canvas,
        array $lines,
        ?string $logoPath,
        QrStickerTenantLogoPlacement $logoPlacement,
    ): void {
        $layout = self::layout($lines, $logoPath, $logoPlacement);
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

        if ($layout['logo'] !== null) {
            self::drawLogoOnImagick($canvas, $layout['logo']);
        }

        if ($layout['text'] !== null) {
            self::drawTextOnImagick($canvas, $layout['text']);
        }
    }

    /**
     * @param  list<string>  $lines
     * @return array{
     *     box_x: int,
     *     box_y: int,
     *     box_width: int,
     *     box_height: int,
     *     text: ?array{
     *         lines: list<string>,
     *         font: string,
     *         font_size: int,
     *         line_height: int,
     *         x: int,
     *         y: int
     *     },
     *     logo: ?array{
     *         path: string,
     *         x: int,
     *         y: int,
     *         width: int,
     *         height: int
     *     }
     * }|null
     */
    private static function layout(array $lines, ?string $logoPath, QrStickerTenantLogoPlacement $logoPlacement): ?array
    {
        $lines = self::normalizedLines($lines);
        $showBottomLogo = self::usesBottomLogo($logoPath, $logoPlacement);

        if ($lines === [] && ! $showBottomLogo) {
            return null;
        }

        $boxX = Avery62x89StickerArtworkLayout::TENANT_DETAILS_PADDING_LEFT_PX;
        $boxWidth = Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX
            - $boxX
            - Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_RIGHT_PX;
        $inset = BrandedQrStickerSurfaceFrame::contentInsetPx();
        $innerWidth = $boxWidth - BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $gap = Avery62x89StickerArtworkLayout::TENANT_DETAILS_LOGO_GAP_PX;

        $logoLayout = null;
        $logoWidth = 0;
        $logoHeight = 0;

        if ($showBottomLogo) {
            [$logoWidth, $logoHeight] = self::fitLogoSize($logoPath);
            $logoX = match ($logoPlacement) {
                QrStickerTenantLogoPlacement::BottomLeft => $boxX + $inset,
                default => $boxX + $boxWidth - $inset - $logoWidth,
            };

            $logoLayout = [
                'path' => (string) $logoPath,
                'x' => $logoX,
                'y' => 0,
                'width' => $logoWidth,
                'height' => $logoHeight,
            ];
        }

        $textMaxWidth = $innerWidth;
        if ($showBottomLogo) {
            $textMaxWidth = max(1, $innerWidth - $logoWidth - $gap);
        }

        $textLayout = null;
        $textHeight = 0;

        if ($lines !== []) {
            $font = BrandedQrStickerFont::headerBoldAbsolutePath();
            $fontSize = self::fitFontSize($lines, $font, $textMaxWidth);
            $lineHeight = (int) round($fontSize * Avery62x89StickerArtworkLayout::TENANT_DETAILS_LINE_HEIGHT_RATIO);
            $textHeight = $lineHeight * count($lines);

            $textX = $boxX + $inset;
            if ($showBottomLogo && $logoPlacement === QrStickerTenantLogoPlacement::BottomLeft) {
                $textX = $boxX + $inset + $logoWidth + $gap;
            }

            $textLayout = [
                'lines' => $lines,
                'font' => $font,
                'font_size' => $fontSize,
                'line_height' => $lineHeight,
                'x' => $textX,
                'y' => 0,
            ];
        }

        $innerHeight = max($textHeight, $logoHeight, 1);
        $boxHeight = $innerHeight + BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        $boxY = Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX
            - Avery62x89StickerArtworkLayout::TENANT_DETAILS_PADDING_BOTTOM_PX
            - $boxHeight;
        $innerY = $boxY + $inset;

        if ($textLayout !== null) {
            $textLayout['y'] = $innerY + (int) floor(($innerHeight - $textHeight) / 2);
        }

        if ($logoLayout !== null) {
            $logoLayout['y'] = $innerY + (int) floor(($innerHeight - $logoHeight) / 2);
        }

        return [
            'box_x' => $boxX,
            'box_y' => $boxY,
            'box_width' => $boxWidth,
            'box_height' => $boxHeight,
            'text' => $textLayout,
            'logo' => $logoLayout,
        ];
    }

    /**
     * @param  array{lines: list<string>, font: string, font_size: int, line_height: int, x: int, y: int}  $text
     * @param  GdImage|resource  $canvas
     */
    private static function drawTextOnGd($canvas, array $text): void
    {
        [$r, $g, $b] = BrandedQrStickerTextColor::darkLabelRgb();
        $color = imagecolorallocate($canvas, $r, $g, $b);
        if ($color === false) {
            throw new \RuntimeException('Unable to allocate branded sticker bottom band text color.');
        }

        $x = $text['x'];
        $y = $text['y'];

        foreach ($text['lines'] as $line) {
            $bbox = imagettfbbox($text['font_size'], 0, $text['font'], $line);
            if (! is_array($bbox)) {
                throw new \RuntimeException('Unable to measure branded sticker bottom band text.');
            }

            $ascent = abs($bbox[7]);
            imagettftext($canvas, $text['font_size'], 0, $x, $y + $ascent, $color, $text['font'], $line);
            $y += $text['line_height'];
        }
    }

    /**
     * @param  array{path: string, x: int, y: int, width: int, height: int}  $logo
     * @param  GdImage|resource  $canvas
     */
    private static function drawLogoOnGd($canvas, array $logo): void
    {
        BrandedQrStickerLogoRaster::compositeOnGd(
            $canvas,
            $logo['path'],
            $logo['x'],
            $logo['y'],
            $logo['width'],
            $logo['height'],
        );
    }

    /**
     * @param  array{lines: list<string>, font: string, font_size: int, line_height: int, x: int, y: int}  $text
     */
    private static function drawTextOnImagick(Imagick $canvas, array $text): void
    {
        [$r, $g, $b] = BrandedQrStickerTextColor::darkLabelRgb();

        $draw = new ImagickDraw;
        $draw->setFont($text['font']);
        $draw->setFontSize((float) $text['font_size']);
        $draw->setFillColor(new ImagickPixel(sprintf('rgb(%d,%d,%d)', $r, $g, $b)));
        $draw->setTextAlignment(Imagick::ALIGN_LEFT);

        $x = (float) $text['x'];
        $y = (float) $text['y'];

        foreach ($text['lines'] as $line) {
            $metrics = $canvas->queryFontMetrics($draw, $line);
            $ascent = is_array($metrics) ? (float) ($metrics['ascender'] ?? $text['font_size']) : (float) $text['font_size'];
            $canvas->annotateImage($draw, $x, $y + $ascent, 0, $line);
            $y += $text['line_height'];
        }
    }

    /**
     * @param  array{path: string, x: int, y: int, width: int, height: int}  $logo
     */
    private static function drawLogoOnImagick(Imagick $canvas, array $logo): void
    {
        BrandedQrStickerLogoRaster::compositeOnImagick(
            $canvas,
            $logo['path'],
            $logo['x'],
            $logo['y'],
            $logo['width'],
            $logo['height'],
        );
    }

    private static function usesBottomLogo(?string $logoPath, QrStickerTenantLogoPlacement $logoPlacement): bool
    {
        if ($logoPath === null || $logoPath === '' || ! is_file($logoPath)) {
            return false;
        }

        return $logoPlacement === QrStickerTenantLogoPlacement::BottomLeft
            || $logoPlacement === QrStickerTenantLogoPlacement::BottomRight;
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
     * @return array{0: int, 1: int}
     */
    private static function fitLogoSize(string $logoPath): array
    {
        if (extension_loaded('imagick')) {
            $probe = QrStickerRasterCache::imagickSource($logoPath);
            if ($probe !== null) {
                $width = $probe->getImageWidth();
                $height = $probe->getImageHeight();

                return self::scaleLogoDimensions($width, $height);
            }
        }

        $probe = QrStickerRasterCache::gdSource($logoPath);
        if ($probe === false) {
            return [1, 1];
        }

        $width = imagesx($probe);
        $height = imagesy($probe);
        imagedestroy($probe);

        return self::scaleLogoDimensions($width, $height);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function scaleLogoDimensions(int $width, int $height): array
    {
        $maxW = Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_WIDTH_PX
            - BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $maxH = Avery62x89StickerArtworkLayout::TENANT_LOGO_MAX_HEIGHT_PX
            - BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        $scale = min($maxW / max(1, $width), $maxH / max(1, $height));

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
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
            throw new \RuntimeException('Unable to measure branded sticker bottom band text width.');
        }

        return (int) abs($bbox[2] - $bbox[0]);
    }
}
