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

    private const LINE_HEIGHT_RATIO = 1.2;

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

        if ($lines !== []) {
            self::drawAddressFrameOnGd($canvas, $lines, $metrics, $width, $height, $logoPlacement, $bottomLogoPath !== null);
        }

        if ($bottomLogoPath !== null) {
            self::drawBottomLogoFrameOnGd($canvas, $bottomLogoPath, $logoPlacement, $metrics, $width, $height);
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

        if ($lines !== []) {
            self::drawAddressFrameOnImagick($canvas, $lines, $metrics, $width, $height, $logoPlacement, $bottomLogoPath !== null);
        }

        if ($bottomLogoPath !== null) {
            self::drawBottomLogoFrameOnImagick($canvas, $bottomLogoPath, $logoPlacement, $metrics, $width, $height);
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
     *     gap: int,
     *     address_max_w: int
     * }
     */
    private static function metrics(int $width, int $height): array
    {
        $short = min($width, $height);

        return [
            'pad_side' => max(10, (int) round($width * 0.035)),
            'pad_top' => max(10, (int) round($height * 0.03)),
            'pad_bottom' => max(8, (int) round($height * 0.022)),
            // Larger logo; compact address card beside it.
            'logo_max_w' => max(56, (int) round($width * 0.24)),
            'logo_max_h' => max(44, (int) round($height * 0.11)),
            'font_max' => max(9, (int) round($short * 0.014)),
            'font_min' => max(7, (int) round($short * 0.01)),
            'gap' => max(6, (int) round($width * 0.015)),
            'address_max_w' => max(64, (int) round($width * 0.42)),
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
     *     gap: int,
     *     address_max_w: int
     * }  $metrics
     * @param  GdImage|resource  $canvas
     */
    private static function drawAddressFrameOnGd(
        $canvas,
        array $lines,
        array $metrics,
        int $canvasWidth,
        int $canvasHeight,
        QrStickerTenantLogoPlacement $logoPlacement,
        bool $hasBottomLogo,
    ): void {
        $layout = self::addressFrameLayout($lines, $metrics, $canvasWidth, $canvasHeight, $logoPlacement, $hasBottomLogo);
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
        self::drawTextOnGd($canvas, $layout['text']);
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
     *     gap: int,
     *     address_max_w: int
     * }  $metrics
     */
    private static function drawAddressFrameOnImagick(
        Imagick $canvas,
        array $lines,
        array $metrics,
        int $canvasWidth,
        int $canvasHeight,
        QrStickerTenantLogoPlacement $logoPlacement,
        bool $hasBottomLogo,
    ): void {
        $layout = self::addressFrameLayout($lines, $metrics, $canvasWidth, $canvasHeight, $logoPlacement, $hasBottomLogo);
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
        self::drawTextOnImagick($canvas, $layout['text']);
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
     *     gap: int,
     *     address_max_w: int
     * }  $metrics
     * @param  GdImage|resource  $canvas
     */
    private static function drawBottomLogoFrameOnGd(
        $canvas,
        string $logoPath,
        QrStickerTenantLogoPlacement $placement,
        array $metrics,
        int $canvasWidth,
        int $canvasHeight,
    ): void {
        [$logoW, $logoH] = self::fitLogoSize($logoPath, $metrics);
        $boxW = $logoW + BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $boxH = $logoH + BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        $boxX = $placement === QrStickerTenantLogoPlacement::BottomLeft
            ? $metrics['pad_side']
            : $canvasWidth - $metrics['pad_side'] - $boxW;
        $boxY = $canvasHeight - $metrics['pad_bottom'] - $boxH;
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
     *     gap: int,
     *     address_max_w: int
     * }  $metrics
     */
    private static function drawBottomLogoFrameOnImagick(
        Imagick $canvas,
        string $logoPath,
        QrStickerTenantLogoPlacement $placement,
        array $metrics,
        int $canvasWidth,
        int $canvasHeight,
    ): void {
        [$logoW, $logoH] = self::fitLogoSize($logoPath, $metrics);
        $boxW = $logoW + BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $boxH = $logoH + BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        $boxX = $placement === QrStickerTenantLogoPlacement::BottomLeft
            ? $metrics['pad_side']
            : $canvasWidth - $metrics['pad_side'] - $boxW;
        $boxY = $canvasHeight - $metrics['pad_bottom'] - $boxH;
        $inset = BrandedQrStickerSurfaceFrame::contentInsetPx();

        BrandedQrStickerSurfaceFrame::drawOnImagick($canvas, $boxX, $boxY, $boxW, $boxH);
        BrandedQrStickerLogoRaster::compositeOnImagick($canvas, $logoPath, $boxX + $inset, $boxY + $inset, $logoW, $logoH);
    }

    /**
     * Compact address card — sized to text, not full page width.
     *
     * @param  list<string>  $lines
     * @param  array{
     *     pad_side: int,
     *     pad_top: int,
     *     pad_bottom: int,
     *     logo_max_w: int,
     *     logo_max_h: int,
     *     font_max: int,
     *     font_min: int,
     *     gap: int,
     *     address_max_w: int
     * }  $metrics
     * @return array{
     *     box_x: int,
     *     box_y: int,
     *     box_width: int,
     *     box_height: int,
     *     text: array{lines: list<string>, font: string, font_size: int, line_height: int, x: int, y: int}
     * }|null
     */
    private static function addressFrameLayout(
        array $lines,
        array $metrics,
        int $canvasWidth,
        int $canvasHeight,
        QrStickerTenantLogoPlacement $logoPlacement,
        bool $hasBottomLogo,
    ): ?array {
        if ($lines === []) {
            return null;
        }

        $inset = BrandedQrStickerSurfaceFrame::contentInsetPx();
        $textMaxWidth = $metrics['address_max_w'];
        if ($hasBottomLogo) {
            $reserved = $metrics['logo_max_w'] + $metrics['gap'];
            $textMaxWidth = min(
                $textMaxWidth,
                max(48, $canvasWidth - (2 * $metrics['pad_side']) - $reserved),
            );
        }

        $font = BrandedQrStickerFont::headerBoldAbsolutePath();
        $fontSize = self::fitFontSize($lines, $font, $textMaxWidth, $metrics);
        $lineHeight = (int) round($fontSize * self::LINE_HEIGHT_RATIO);
        $textHeight = $lineHeight * count($lines);
        $textWidth = 0;
        foreach ($lines as $line) {
            $bbox = imagettfbbox($fontSize, 0, $font, $line);
            $textWidth = max($textWidth, is_array($bbox) ? (int) abs($bbox[2] - $bbox[0]) : 0);
        }
        $textWidth = min($textMaxWidth, max(1, $textWidth));

        $boxWidth = $textWidth + BrandedQrStickerSurfaceFrame::horizontalOverheadPx();
        $boxHeight = $textHeight + BrandedQrStickerSurfaceFrame::verticalOverheadPx();
        $boxY = $canvasHeight - $metrics['pad_bottom'] - $boxHeight;

        // Keep address opposite the bottom logo when both are present.
        $boxX = $metrics['pad_side'];
        if ($hasBottomLogo && $logoPlacement === QrStickerTenantLogoPlacement::BottomLeft) {
            $boxX = $canvasWidth - $metrics['pad_side'] - $boxWidth;
        }

        return [
            'box_x' => $boxX,
            'box_y' => $boxY,
            'box_width' => $boxWidth,
            'box_height' => $boxHeight,
            'text' => [
                'lines' => $lines,
                'font' => $font,
                'font_size' => $fontSize,
                'line_height' => $lineHeight,
                'x' => $boxX + $inset,
                'y' => $boxY + $inset,
            ],
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
     *     gap: int,
     *     address_max_w: int
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
     *     gap: int,
     *     address_max_w: int
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
     *     gap: int,
     *     address_max_w: int
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
            $fontMetrics = $canvas->queryFontMetrics($draw, $line);
            $ascent = is_array($fontMetrics) ? (float) ($fontMetrics['ascender'] ?? $text['font_size']) : (float) $text['font_size'];
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

            if ($fits) {
                return $fontSize;
            }
        }

        return $metrics['font_min'];
    }
}
