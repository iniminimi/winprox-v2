<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Tenant;
use Imagick;
use ImagickPixel;
use RuntimeException;

/**
 * Settings preview for A6/A5/A4 printable backgrounds with live QR + logo/address overlays.
 */
final class QrPrintablePagePreviewComposer
{
    private const PREVIEW_MAX_WIDTH_PX = 280;

    private const DEMO_REPORT_URL = 'https://example.test/melden/preview';

    private const DEMO_PRIMARY_LABEL = 'Winprox-2606-00001';

    /** QR size as fraction of the preview's shorter side (matches Word export). */
    private const QR_SIZE_RATIO = 0.48;

    public function composePngBytes(
        string $backgroundPath,
        ?Tenant $tenant,
        BrandedQrStickerLayoutConfig $layout,
        ?string $centerLogoPath = null,
        ?string $secondaryLabel = null,
    ): string {
        if (class_exists(Imagick::class)) {
            return $this->composeWithImagick($backgroundPath, $tenant, $layout, $centerLogoPath, $secondaryLabel);
        }

        return $this->composeWithGd($backgroundPath, $tenant, $layout, $centerLogoPath, $secondaryLabel);
    }

    private function composeWithImagick(
        string $backgroundPath,
        ?Tenant $tenant,
        BrandedQrStickerLayoutConfig $layout,
        ?string $centerLogoPath,
        ?string $secondaryLabel,
    ): string {
        try {
            $image = new Imagick;
            $image->setBackgroundColor(new ImagickPixel('white'));
            $image->readImage($backgroundPath);
            $image->setImageBackgroundColor(new ImagickPixel('white'));
            $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $image->clear();
            $image = $flattened;

            $srcW = max(1, $image->getImageWidth());
            $srcH = max(1, $image->getImageHeight());
            $scale = min(1.0, self::PREVIEW_MAX_WIDTH_PX / $srcW);
            $width = max(1, (int) round($srcW * $scale));
            $height = max(1, (int) round($srcH * $scale));

            $canvas = new Imagick;
            $canvas->newImage($width, $height, new ImagickPixel('white'));
            $canvas->setImageFormat('png');
            $image->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1, true);
            $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, 0, 0);
            $image->clear();

            $this->drawQrAndLabelsOnImagick($canvas, $centerLogoPath, $secondaryLabel);
            $this->drawBrandingOnImagick($canvas, $tenant, $layout);

            $bytes = $canvas->getImageBlob();
            $canvas->clear();

            return $bytes;
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Unable to compose printable QR page preview: '.$exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    private function composeWithGd(
        string $backgroundPath,
        ?Tenant $tenant,
        BrandedQrStickerLayoutConfig $layout,
        ?string $centerLogoPath,
        ?string $secondaryLabel,
    ): string {
        $source = QrStickerRasterCache::gdSource($backgroundPath);
        if ($source === false) {
            throw new RuntimeException(
                'Unable to load printable QR page preview background. Provide a PNG or enable PHP imagick for SVG.',
            );
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $scale = min(1.0, self::PREVIEW_MAX_WIDTH_PX / max(1, $srcW));
        $width = max(1, (int) round($srcW * $scale));
        $height = max(1, (int) round($srcH * $scale));

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            throw new RuntimeException('Unable to allocate printable QR page preview canvas.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        if ($white !== false) {
            imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        }

        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $srcW, $srcH);

        $this->drawQrAndLabelsOnGd($canvas, $centerLogoPath, $secondaryLabel);
        $this->drawBrandingOnGd($canvas, $tenant, $layout);

        ob_start();
        $ok = imagepng($canvas);
        imagedestroy($canvas);
        $bytes = ob_get_clean();

        if (! $ok || ! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Unable to encode printable QR page preview PNG.');
        }

        return $bytes;
    }

    private function drawQrAndLabelsOnImagick(
        Imagick $canvas,
        ?string $centerLogoPath,
        ?string $secondaryLabel,
    ): void {
        $width = $canvas->getImageWidth();
        $height = $canvas->getImageHeight();
        $layout = $this->qrLayout($width, $height, $secondaryLabel);

        $qrBytes = QrCodePngWriter::writeStringWithCenterLogo(
            self::DEMO_REPORT_URL,
            max(240, $layout['qrPx']),
            QrLogoLayout::STICKER_BOX_RATIO,
            $centerLogoPath,
        );
        $qr = new Imagick;
        $qr->readImageBlob($qrBytes);
        $qr->resizeImage($layout['qrPx'], $layout['qrPx'], Imagick::FILTER_LANCZOS, 1, true);
        $canvas->compositeImage($qr, Imagick::COMPOSITE_OVER, $layout['qrX'], $layout['qrY']);
        $qr->clear();

        $centerX = (int) round($width / 2);
        $this->drawImagickCenteredText(
            $canvas,
            self::DEMO_PRIMARY_LABEL,
            $centerX,
            $layout['primaryY'],
            $layout['primaryFontPx'],
            QrPrintablePageFont::semiboldAbsolutePath(),
        );
        if ($layout['secondaryText'] !== '') {
            $this->drawImagickCenteredText(
                $canvas,
                $layout['secondaryText'],
                $centerX,
                $layout['secondaryY'],
                $layout['secondaryFontPx'],
                QrPrintablePageFont::regularAbsolutePath(),
            );
        }
    }

    /**
     * @param  \GdImage  $canvas
     */
    private function drawQrAndLabelsOnGd(
        $canvas,
        ?string $centerLogoPath,
        ?string $secondaryLabel,
    ): void {
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $layout = $this->qrLayout($width, $height, $secondaryLabel);

        $qrBytes = QrCodePngWriter::writeStringWithCenterLogo(
            self::DEMO_REPORT_URL,
            max(240, $layout['qrPx']),
            QrLogoLayout::STICKER_BOX_RATIO,
            $centerLogoPath,
        );
        $qr = @imagecreatefromstring($qrBytes);
        if ($qr === false) {
            return;
        }

        $qrScaled = imagecreatetruecolor($layout['qrPx'], $layout['qrPx']);
        if ($qrScaled === false) {
            imagedestroy($qr);

            return;
        }

        imagealphablending($qrScaled, false);
        imagesavealpha($qrScaled, true);
        imagecopyresampled($qrScaled, $qr, 0, 0, 0, 0, $layout['qrPx'], $layout['qrPx'], imagesx($qr), imagesy($qr));
        imagedestroy($qr);

        imagealphablending($canvas, true);
        imagecopy($canvas, $qrScaled, $layout['qrX'], $layout['qrY'], 0, 0, $layout['qrPx'], $layout['qrPx']);
        imagedestroy($qrScaled);

        $color = imagecolorallocate($canvas, 17, 24, 39);
        if ($color === false) {
            return;
        }

        $this->drawGdCenteredText(
            $canvas,
            self::DEMO_PRIMARY_LABEL,
            $width,
            $layout['primaryY'],
            $layout['primaryFontPx'],
            $color,
            QrPrintablePageFont::semiboldAbsolutePath(),
        );
        if ($layout['secondaryText'] !== '') {
            $this->drawGdCenteredText(
                $canvas,
                $layout['secondaryText'],
                $width,
                $layout['secondaryY'],
                $layout['secondaryFontPx'],
                $color,
                QrPrintablePageFont::regularAbsolutePath(),
            );
        }
    }

    /**
     * @return array{
     *     qrPx: int,
     *     qrX: int,
     *     qrY: int,
     *     primaryY: int,
     *     secondaryY: int,
     *     primaryFontPx: float,
     *     secondaryFontPx: float,
     *     secondaryText: string
     * }
     */
    private function qrLayout(int $width, int $height, ?string $secondaryLabel): array
    {
        $qrPx = max(64, (int) round(min($width, $height) * self::QR_SIZE_RATIO));
        $qrX = (int) round(($width - $qrPx) / 2);
        $primaryFontPx = max(9.0, $height * 0.026);
        $secondaryFontPx = max(12.0, $height * 0.038);
        $labelGap = max(4, (int) round($height * 0.012));
        $lineGap = max(2, (int) round($height * 0.008));
        $secondaryText = trim((string) $secondaryLabel);

        $labelBlock = $labelGap + (int) ceil($primaryFontPx) + 2;
        if ($secondaryText !== '') {
            $labelBlock += $lineGap + (int) ceil($secondaryFontPx) + 2;
        }

        $blockHeight = $qrPx + $labelBlock;
        $qrY = (int) round(($height - $blockHeight) / 2);
        $primaryY = $qrY + $qrPx + $labelGap + (int) round($primaryFontPx);
        $secondaryY = $primaryY + $lineGap + (int) round($secondaryFontPx);

        return [
            'qrPx' => $qrPx,
            'qrX' => $qrX,
            'qrY' => $qrY,
            'primaryY' => $primaryY,
            'secondaryY' => $secondaryY,
            'primaryFontPx' => $primaryFontPx,
            'secondaryFontPx' => $secondaryFontPx,
            'secondaryText' => $secondaryText,
        ];
    }

    private function drawImagickCenteredText(
        Imagick $canvas,
        string $text,
        int $centerX,
        int $baselineY,
        float $fontSizePx,
        string $fontPath,
    ): void {
        $draw = new \ImagickDraw;
        $draw->setFillColor(new ImagickPixel('#111827'));
        $draw->setFont($fontPath);
        $draw->setFontSize($fontSizePx);
        $draw->setTextAlignment(Imagick::ALIGN_CENTER);
        $canvas->annotateImage($draw, $centerX, $baselineY, 0, $text);
    }

    /**
     * @param  \GdImage  $canvas
     */
    private function drawGdCenteredText(
        $canvas,
        string $text,
        int $canvasWidthPx,
        int $baselineY,
        float $fontSizePx,
        int $color,
        string $fontPath,
    ): void {
        $bbox = imagettfbbox($fontSizePx, 0, $fontPath, $text);
        $textWidth = is_array($bbox) ? (int) abs($bbox[2] - $bbox[0]) : 0;
        $textX = (int) round(($canvasWidthPx - $textWidth) / 2);
        imagettftext($canvas, $fontSizePx, 0, $textX, $baselineY, $color, $fontPath, $text);
    }

    private function drawBrandingOnImagick(
        Imagick $canvas,
        ?Tenant $tenant,
        BrandedQrStickerLayoutConfig $layout,
    ): void {
        $addressLines = $layout->showTenantAddress()
            ? BrandedQrStickerTenantDetails::lines($tenant)
            : [];
        $logoPath = $layout->showTenantLogoOnSticker()
            ? QrCenterLogo::tenantLogoAbsolutePath($tenant)
            : null;

        QrPrintablePageTenantBranding::drawOnImagick(
            $canvas,
            $addressLines,
            $logoPath,
            $layout->tenantLogoPlacement(),
            $layout->tenantAddressPlacement(),
        );
    }

    /**
     * @param  \GdImage  $canvas
     */
    private function drawBrandingOnGd(
        $canvas,
        ?Tenant $tenant,
        BrandedQrStickerLayoutConfig $layout,
    ): void {
        $addressLines = $layout->showTenantAddress()
            ? BrandedQrStickerTenantDetails::lines($tenant)
            : [];
        $logoPath = $layout->showTenantLogoOnSticker()
            ? QrCenterLogo::tenantLogoAbsolutePath($tenant)
            : null;

        QrPrintablePageTenantBranding::drawOnGd(
            $canvas,
            $addressLines,
            $logoPath,
            $layout->tenantLogoPlacement(),
            $layout->tenantAddressPlacement(),
        );
    }
}
