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
        ?string $footerText = null,
    ): void {
        $bytes = $this->compositeBytes($backgroundPath, $reportUrl, $centerLogoPath, $headerText, $footerText);

        if (file_put_contents($absoluteOutputPath, $bytes) === false) {
            throw new RuntimeException('Unable to write branded QR sticker PNG.');
        }
    }

    public function compositeBytes(
        string $backgroundPath,
        string $reportUrl,
        ?string $centerLogoPath = null,
        ?string $headerText = null,
        ?string $footerText = null,
    ): string {
        if (! is_file($backgroundPath)) {
            throw new RuntimeException('Branded sticker background file is missing.');
        }

        $qrBytes = QrCodePngWriter::writeStringWithCenterLogo(
            $reportUrl,
            Avery62x89StickerArtworkLayout::qrRenderPixelSize(),
            QrLogoLayout::STICKER_BOX_RATIO,
            $centerLogoPath,
        );

        if (extension_loaded('imagick')) {
            return $this->compositeWithImagick($backgroundPath, $qrBytes, $headerText, $footerText);
        }

        if (extension_loaded('gd')) {
            return $this->compositeWithGd($backgroundPath, $qrBytes, $headerText, $footerText);
        }

        throw new RuntimeException('Branded QR sticker export requires the PHP gd or imagick extension.');
    }

    private function compositeWithGd(string $backgroundPath, string $qrBytes, ?string $headerText, ?string $footerText): string
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

        if ($footerText !== null && trim($footerText) !== '') {
            BrandedQrStickerFooterRenderer::drawOnGd($canvas, $footerText);
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

    private function compositeWithImagick(string $backgroundPath, string $qrBytes, ?string $headerText, ?string $footerText): string
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

        if ($footerText !== null && trim($footerText) !== '') {
            BrandedQrStickerFooterRenderer::drawOnImagick($canvas, $footerText);
        }

        $canvas->setImageFormat('png');
        $bytes = $canvas->getImagesBlob();

        $qr->clear();
        $canvas->clear();

        return $bytes;
    }
}
