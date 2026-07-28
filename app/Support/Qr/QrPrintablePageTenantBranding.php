<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Enums\QrStickerTenantLogoPlacement;
use GdImage;
use Imagick;
use ImagickDraw;
use ImagickPixel;

/**
 * Tenant logo + address overlays for A6/A5/A4 printable QR pages.
 * Positions scale with the composed page canvas (same placements as Avery 62×89-R).
 */
final class QrPrintablePageTenantBranding
{
    private const MAX_ADDRESS_LINES = 3;

    private const LINE_HEIGHT_RATIO = 1.38;

    /**
     * @param  list<string>  $addressLines
     * @param  GdImage|resource  $canvas
     */
    public static function drawOnGd(
        $canvas,
        array $addressLines,
        ?string $logoPath,
        QrStickerTenantLogoPlacement $logoPlacement,
        QrStickerTenantLogoPlacement $addressPlacement,
    ): void {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $metrics = self::metrics($width, $height);

        if (self::usesTopLogo($logoPath, $logoPlacement)) {
            self::drawCornerLogoOnGd($canvas, (string) $logoPath, $logoPlacement, $metrics);
        }

        $lines = $addressPlacement === QrStickerTenantLogoPlacement::BottomLeft
            ? self::normalizedLines($addressLines)
            : [];
        $bottomLogoPath = self::usesBottomLogo($logoPath, $logoPlacement) ? $logoPath : null;

        if ($lines === [] && $bottomLogoPath === null) {
            return;
        }

        $layout = self::bottomBandLayout($width, $height, $lines, $bottomLogoPath, $logoPlacement, $metrics);
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
            BrandedQrStickerLogoRaster::compositeOnGd(
                $canvas,
                $layout['logo']['path'],
                $layout['logo']['x'],
                $layout['logo']['y'],
                $layout['logo']['width'],
                $layout['logo']['height'],
            );
        }

        if ($layout['text'] !== null) {
            self::drawTextOnGd($canvas, $layout['text']);
        }
    }

    /**
     * @param  list<string>  $addressLines
     */
    public static function drawOnImagick(
        Imagick $canvas,
        array $addressLines,
        ?string $logoPath,
        QrStickerTenantLogoPlacement $logoPlacement,
        QrStickerTenantLogoPlacement $addressPlacement,
    ): void {
        $width = $canvas->getImageWidth();
        $height = $canvas->getImageHeight();
        $metrics = self::metrics($width, $height);

        if (self::usesTopLogo($logoPath, $logoPlacement)) {
            self::drawCornerLogoOnImagick($canvas, (string) $logoPath, $logoPlacement, $metrics);
        }

        $lines = $addressPlacement === QrStickerTenantLogoPlacement::BottomLeft
            ? self::normalizedLines($addressLines)
            : [];
        $bottomLogoPath = self::usesBottomLogo($logoPath, $logoPlacement) ? $logoPath : null;

        if ($lines === [] && $bottomLogoPath === null) {
            return;
        }

        $layout = self::bottomBandLayout($width, $height, $lines, $bottomLogoPath, $logoPlacement, $metrics);
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
            BrandedQrStickerLogoRaster::compositeOnImagick(
                $canvas,
                $layout['logo']['path'],
                $layout['logo']['x'],
                $layout['logo']['y'],
                $layout['logo']['width'],
                $layout['logo']['height'],
            );
        }

        if ($layout['text'] !== null) {
            self::drawTextOnImagick($canvas, $layout['text']);
        }
    }

    /**
     * @return array{
     *     pad_side: int,
     *     pad_top: int,
     *     pad_bottom: int,
     *     logo_max_w: int,
     *     logo_max_h: int,
     *     font_max: int,
     *     font_min: int,
     *     gap: int
     * }
     */
    private static function metrics(int $width, int $height): array
    {
        $short = min($width, $height);

        return [
            'pad_side' => max(12, (int) round($width * 0.04)),
            'pad_top' => max(12, (int) round($height * 0.035)),
            'pad_bottom' => max(10, (int) round($height * 0.03)),
            'logo_max_w' => max(48, (int) round($width * 0.16)),
            'logo_max_h' => max(36, (int) round($height * 0.065)),
            'font_max' => max(14, (int) round($short * 0.026)),
            'font_min' => max(10, (int) round($short * 0.016)),
            'gap' => max(8, (int) round($width * 0.02)),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @param  array{
     *     pad_side: int,
     *     pad_top: int,
     *     pad_bottom: int,
     *     logo_max_w: int,
     *     logo_max_h: int,
     *     font_max: int,
     *     font_min: int,
     *     gap: int
     * }  $metrics
     * @return array{
     *     box_x: int,
     *     box_y: int,
     *     box_width: int,
     *     box_height: int,
     *     text: ?array{lines: list<string>, font: string, font_size: int, line_height: int, x: int, y: int},
     *     logo: ?array{path: string, x: int, y: int, width: int, height: int}
     * }|null
     */
    private static function bottomBandLayout(
        int $canvasWidth,
        int $canvasHeight,
        array $lines,
        ?string $logoPath,
        QrStickerTenantLogoPlacement $logoPlacement,
        array $metrics,
    ): ?array {
        $showBottomLogo = $logoPath !== null && $logoPath !== '' && is_file($logoPath);
        if ($lines === [] && ! $showBottomLogo) {
            return null;
        }

        $boxX = $metrics['pad_side'];
        $boxWidth = $canvasWidth - $metrics['pad_side'] - $metrics['pad_side'];
        $inset = BrandedQrStickerSurfaceFrame::contentInsetPx();
        $innerWidth = $boxWidth - BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $gap = $metrics['gap'];

        $logoLayout = null;
        $logoWidth = 0;
        $logoHeight = 0;

        if ($showBottomLogo) {
            [$logoWidth, $logoHeight] = self::fitLogoSize($logoPath, $metrics);
            $logoX = match ($logoPlacement) {
                QrStickerTenantLogoPlacement::BottomLeft => $boxX + $inset,
                default => $boxX + $boxWidth - $inset - $logoWidth,
            };
            $logoLayout = [
                'path' => $logoPath,
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
            $fontSize = self::fitFontSize($lines, $font, $textMaxWidth, $metrics);
            $lineHeight = (int) round($fontSize * self::LINE_HEIGHT_RATIO);
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
        $boxY = $canvasHeight - $metrics['pad_bottom'] - $boxHeight;
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
     * @param  array{
     *     pad_side: int,
     *     pad_top: int,
     *     pad_bottom: int,
     *     logo_max_w: int,
     *     logo_max_h: int,
     *     font_max: int,
     *     font_min: int,
     *     gap: int
     * }  $metrics
     * @param  GdImage|resource  $canvas
     */
    private static function drawCornerLogoOnGd(
        $canvas,
        string $logoPath,
        QrStickerTenantLogoPlacement $placement,
        array $metrics,
    ): void {
        [$logoW, $logoH] = self::fitLogoSize($logoPath, $metrics);
        $boxW = $logoW + BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $boxH = $logoH + BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        [$boxX, $boxY] = self::cornerOrigin($placement, imagesx($canvas), imagesy($canvas), $boxW, $boxH, $metrics);
        $inset = BrandedQrStickerSurfaceFrame::contentInsetPx();

        BrandedQrStickerSurfaceFrame::drawOnGd($canvas, $boxX, $boxY, $boxW, $boxH);
        BrandedQrStickerLogoRaster::compositeOnGd($canvas, $logoPath, $boxX + $inset, $boxY + $inset, $logoW, $logoH);
    }

    /**
     * @param  array{
     *     pad_side: int,
     *     pad_top: int,
     *     pad_bottom: int,
     *     logo_max_w: int,
     *     logo_max_h: int,
     *     font_max: int,
     *     font_min: int,
     *     gap: int
     * }  $metrics
     */
    private static function drawCornerLogoOnImagick(
        Imagick $canvas,
        string $logoPath,
        QrStickerTenantLogoPlacement $placement,
        array $metrics,
    ): void {
        [$logoW, $logoH] = self::fitLogoSize($logoPath, $metrics);
        $boxW = $logoW + BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $boxH = $logoH + BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        [$boxX, $boxY] = self::cornerOrigin(
            $placement,
            $canvas->getImageWidth(),
            $canvas->getImageHeight(),
            $boxW,
            $boxH,
            $metrics,
        );
        $inset = BrandedQrStickerSurfaceFrame::contentInsetPx();

        BrandedQrStickerSurfaceFrame::drawOnImagick($canvas, $boxX, $boxY, $boxW, $boxH);
        BrandedQrStickerLogoRaster::compositeOnImagick($canvas, $logoPath, $boxX + $inset, $boxY + $inset, $logoW, $logoH);
    }

    /**
     * @param  array{
     *     pad_side: int,
     *     pad_top: int,
     *     pad_bottom: int,
     *     logo_max_w: int,
     *     logo_max_h: int,
     *     font_max: int,
     *     font_min: int,
     *     gap: int
     * }  $metrics
     * @return array{0: int, 1: int}
     */
    private static function cornerOrigin(
        QrStickerTenantLogoPlacement $placement,
        int $canvasWidth,
        int $canvasHeight,
        int $boxWidth,
        int $boxHeight,
        array $metrics,
    ): array {
        return match ($placement) {
            QrStickerTenantLogoPlacement::TopLeft => [$metrics['pad_side'], $metrics['pad_top']],
            QrStickerTenantLogoPlacement::TopRight => [
                $canvasWidth - $metrics['pad_side'] - $boxWidth,
                $metrics['pad_top'],
            ],
            default => [0, 0],
        };
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
            return;
        }

        $x = $text['x'];
        $y = $text['y'];
        foreach ($text['lines'] as $line) {
            $bbox = imagettfbbox($text['font_size'], 0, $text['font'], $line);
            $ascent = is_array($bbox) ? abs($bbox[7]) : $text['font_size'];
            imagettftext($canvas, $text['font_size'], 0, $x, $y + $ascent, $color, $text['font'], $line);
            $y += $text['line_height'];
        }
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

    private static function usesTopLogo(?string $logoPath, QrStickerTenantLogoPlacement $placement): bool
    {
        if ($logoPath === null || $logoPath === '' || ! is_file($logoPath)) {
            return false;
        }

        return $placement === QrStickerTenantLogoPlacement::TopLeft
            || $placement === QrStickerTenantLogoPlacement::TopRight;
    }

    private static function usesBottomLogo(?string $logoPath, QrStickerTenantLogoPlacement $placement): bool
    {
        if ($logoPath === null || $logoPath === '' || ! is_file($logoPath)) {
            return false;
        }

        return $placement === QrStickerTenantLogoPlacement::BottomLeft
            || $placement === QrStickerTenantLogoPlacement::BottomRight;
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

        return array_slice($normalized, 0, self::MAX_ADDRESS_LINES);
    }

    /**
     * @param  array{logo_max_w: int, logo_max_h: int}  $metrics
     * @return array{0: int, 1: int}
     */
    private static function fitLogoSize(string $logoPath, array $metrics): array
    {
        $maxW = $metrics['logo_max_w'] - BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $maxH = $metrics['logo_max_h'] - BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        $maxW = max(1, $maxW);
        $maxH = max(1, $maxH);

        if (extension_loaded('imagick')) {
            $probe = QrStickerRasterCache::imagickSource($logoPath);
            if ($probe !== null) {
                return self::scaleDimensions($probe->getImageWidth(), $probe->getImageHeight(), $maxW, $maxH);
            }
        }

        $probe = QrStickerRasterCache::gdSource($logoPath);
        if ($probe === false) {
            return [1, 1];
        }

        $result = self::scaleDimensions(imagesx($probe), imagesy($probe), $maxW, $maxH);
        imagedestroy($probe);

        return $result;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function scaleDimensions(int $width, int $height, int $maxW, int $maxH): array
    {
        $scale = min($maxW / max(1, $width), $maxH / max(1, $height));

        return [
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        ];
    }

    /**
     * @param  list<string>  $lines
     * @param  array{font_max: int, font_min: int, logo_max_h: int}  $metrics
     */
    private static function fitFontSize(array $lines, string $fontPath, int $maxWidth, array $metrics): int
    {
        for ($fontSize = $metrics['font_max']; $fontSize >= $metrics['font_min']; $fontSize--) {
            $fits = true;
            foreach ($lines as $line) {
                $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
                $width = is_array($bbox) ? (int) abs($bbox[2] - $bbox[0]) : $maxWidth + 1;
                if ($width > $maxWidth) {
                    $fits = false;
                    break;
                }
            }

            $lineHeight = $fontSize * self::LINE_HEIGHT_RATIO;
            if ($fits && ($lineHeight * count($lines)) <= $metrics['logo_max_h'] + 24) {
                return $fontSize;
            }
        }

        return $metrics['font_min'];
    }
}
