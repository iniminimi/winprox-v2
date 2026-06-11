<?php

declare(strict_types=1);

namespace App\Support\Qr;

use Imagick;
use RuntimeException;

/**
 * Composite QR onto a full-size sticker artwork PNG (branded Avery sheets).
 */
final class BrandedQrStickerCompositor
{
    public function writeFile(
        string $backgroundPath,
        string $reportUrl,
        string $absoluteOutputPath,
        ?string $centerLogoPath = null,
        ?string $headerText = null,
        ?string $unitCaptionText = null,
        ?string $footerText = null,
        ?array $tenantDetailLines = null,
        ?string $tenantCornerLogoPath = null,
        bool $includeCenterLogo = true,
    ): void {
        $bytes = $this->compositeBytes(
            $backgroundPath,
            $reportUrl,
            $centerLogoPath,
            $headerText,
            $unitCaptionText,
            $footerText,
            $tenantDetailLines,
            $tenantCornerLogoPath,
            $includeCenterLogo,
        );

        if (file_put_contents($absoluteOutputPath, $bytes) === false) {
            throw new RuntimeException('Unable to write branded QR sticker PNG.');
        }
    }

    public function compositeBytes(
        string $backgroundPath,
        string $reportUrl,
        ?string $centerLogoPath = null,
        ?string $headerText = null,
        ?string $unitCaptionText = null,
        ?string $footerText = null,
        ?array $tenantDetailLines = null,
        ?string $tenantCornerLogoPath = null,
        bool $includeCenterLogo = true,
    ): string {
        if (! is_file($backgroundPath)) {
            throw new RuntimeException('Branded sticker background file is missing.');
        }

        $qrBytes = $this->generateQrBytes($reportUrl, $includeCenterLogo, $centerLogoPath);

        if (extension_loaded('imagick')) {
            return $this->compositeWithImagick(
                $backgroundPath,
                $qrBytes,
                $headerText,
                $unitCaptionText,
                $footerText,
                $tenantDetailLines,
                $tenantCornerLogoPath,
            );
        }

        if (extension_loaded('gd')) {
            return $this->compositeWithGd(
                $backgroundPath,
                $qrBytes,
                $headerText,
                $unitCaptionText,
                $footerText,
                $tenantDetailLines,
                $tenantCornerLogoPath,
            );
        }

        throw new RuntimeException('Branded QR sticker export requires the PHP gd or imagick extension.');
    }

    private function generateQrBytes(string $reportUrl, bool $includeCenterLogo, ?string $centerLogoPath): string
    {
        $pixelSize = Avery62x89StickerArtworkLayout::qrRenderPixelSize();

        if (! $includeCenterLogo) {
            return QrCodePngWriter::writeString($reportUrl, $pixelSize);
        }

        return QrCodePngWriter::writeStringWithCenterLogo(
            $reportUrl,
            $pixelSize,
            QrLogoLayout::STICKER_BOX_RATIO,
            $centerLogoPath,
        );
    }

    private function compositeWithGd(
        string $backgroundPath,
        string $qrBytes,
        ?string $headerText,
        ?string $unitCaptionText,
        ?string $footerText,
        ?array $tenantDetailLines,
        ?string $tenantCornerLogoPath,
    ): string
    {
        $loaded = @imagecreatefrompng($backgroundPath);
        if ($loaded === false) {
            $loaded = @imagecreatefromjpeg($backgroundPath);
        }
        if ($loaded === false) {
            throw new RuntimeException('Unable to load branded sticker background image.');
        }

        $canvas = $this->normalizeCanvasWithGd($loaded);
        imagedestroy($loaded);

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        if ($headerText !== null && trim($headerText) !== '') {
            BrandedQrStickerHeaderRenderer::drawOnGd($canvas, $headerText);
        }

        $qr = imagecreatefromstring($qrBytes);
        if ($qr === false) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to decode QR PNG for branded sticker.');
        }

        $qrWidth = imagesx($qr);
        $qrHeight = imagesy($qr);
        $destSize = Avery62x89StickerArtworkLayout::QR_SIZE_PX;
        $destX = Avery62x89StickerArtworkLayout::qrLeftPx();
        $destY = Avery62x89StickerArtworkLayout::qrTopPx();

        imagecopyresampled(
            $canvas,
            $qr,
            $destX,
            $destY,
            0,
            0,
            $destSize,
            $destSize,
            $qrWidth,
            $qrHeight,
        );

        imagedestroy($qr);

        if ($unitCaptionText !== null && trim($unitCaptionText) !== '') {
            BrandedQrStickerUnitCaptionRenderer::drawOnGd($canvas, $unitCaptionText);
        }

        if ($footerText !== null && trim($footerText) !== '') {
            BrandedQrStickerFooterRenderer::drawOnGd($canvas, $footerText);
        }

        $hasCornerLogo = $tenantCornerLogoPath !== null && $tenantCornerLogoPath !== '' && is_file($tenantCornerLogoPath);
        if ($tenantDetailLines !== null && $tenantDetailLines !== []) {
            BrandedQrStickerTenantDetailsRenderer::drawOnGd($canvas, $tenantDetailLines, $hasCornerLogo);
        }

        if ($hasCornerLogo) {
            BrandedQrStickerTenantLogoRenderer::drawOnGd($canvas, $tenantCornerLogoPath);
        }

        ob_start();
        imagepng($canvas);
        $bytes = ob_get_clean();
        imagedestroy($canvas);

        if ($bytes === false) {
            throw new RuntimeException('Unable to encode branded QR sticker PNG.');
        }

        return $bytes;
    }

    /**
     * @return \GdImage
     */
    private function normalizeCanvasWithGd(\GdImage $loaded): \GdImage
    {
        $width = imagesx($loaded);
        $height = imagesy($loaded);

        $canvas = imagecreatetruecolor(
            Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX,
            Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX,
        );
        if ($canvas === false) {
            throw new RuntimeException('Unable to allocate branded sticker canvas.');
        }

        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);

        imagecopyresampled(
            $canvas,
            $loaded,
            0,
            0,
            0,
            0,
            Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX,
            Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX,
            $width,
            $height,
        );

        return $canvas;
    }

    private function compositeWithImagick(
        string $backgroundPath,
        string $qrBytes,
        ?string $headerText,
        ?string $unitCaptionText,
        ?string $footerText,
        ?array $tenantDetailLines,
        ?string $tenantCornerLogoPath,
    ): string
    {
        $canvas = new Imagick($backgroundPath);
        if ($canvas->getImageWidth() !== Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX
            || $canvas->getImageHeight() !== Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX) {
            $canvas->resizeImage(
                Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX,
                Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX,
                Imagick::FILTER_LANCZOS,
                1,
            );
        }

        if ($headerText !== null && trim($headerText) !== '') {
            BrandedQrStickerHeaderRenderer::drawOnImagick($canvas, $headerText);
        }

        $qr = new Imagick;
        $qr->readImageBlob($qrBytes);
        $qr->resizeImage(
            Avery62x89StickerArtworkLayout::QR_SIZE_PX,
            Avery62x89StickerArtworkLayout::QR_SIZE_PX,
            Imagick::FILTER_LANCZOS,
            1,
        );

        $canvas->compositeImage(
            $qr,
            Imagick::COMPOSITE_OVER,
            Avery62x89StickerArtworkLayout::qrLeftPx(),
            Avery62x89StickerArtworkLayout::qrTopPx(),
        );

        if ($unitCaptionText !== null && trim($unitCaptionText) !== '') {
            BrandedQrStickerUnitCaptionRenderer::drawOnImagick($canvas, $unitCaptionText);
        }

        if ($footerText !== null && trim($footerText) !== '') {
            BrandedQrStickerFooterRenderer::drawOnImagick($canvas, $footerText);
        }

        $hasCornerLogo = $tenantCornerLogoPath !== null && $tenantCornerLogoPath !== '' && is_file($tenantCornerLogoPath);
        if ($tenantDetailLines !== null && $tenantDetailLines !== []) {
            BrandedQrStickerTenantDetailsRenderer::drawOnImagick($canvas, $tenantDetailLines, $hasCornerLogo);
        }

        if ($hasCornerLogo) {
            BrandedQrStickerTenantLogoRenderer::drawOnImagick($canvas, $tenantCornerLogoPath);
        }

        $canvas->setImageFormat('png');
        $bytes = $canvas->getImagesBlob();

        $qr->clear();
        $canvas->clear();

        return $bytes;
    }
}
