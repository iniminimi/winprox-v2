<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Tenant;
use Imagick;
use ImagickPixel;
use RuntimeException;

/**
 * Settings preview for A6/A5/A4 printable backgrounds with live logo/address overlays.
 */
final class QrPrintablePagePreviewComposer
{
    private const PREVIEW_MAX_WIDTH_PX = 280;

    public function composePngBytes(
        string $backgroundPath,
        ?Tenant $tenant,
        BrandedQrStickerLayoutConfig $layout,
    ): string {
        if (class_exists(Imagick::class)) {
            return $this->composeWithImagick($backgroundPath, $tenant, $layout);
        }

        return $this->composeWithGd($backgroundPath, $tenant, $layout);
    }

    private function composeWithImagick(
        string $backgroundPath,
        ?Tenant $tenant,
        BrandedQrStickerLayoutConfig $layout,
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
