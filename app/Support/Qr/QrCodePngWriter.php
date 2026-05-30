<?php

declare(strict_types=1);

namespace App\Support\Qr;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererInterface;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Imagick;
use RuntimeException;

final class QrCodePngWriter
{
    private const QUIET_ZONE_MODULES = 2;

    private const LOGO_BOX_RATIO = 0.30;

    /** Smaller center logo for small printed sticker sheets (better scan reliability). */
    private const STICKER_LOGO_BOX_RATIO = 0.22;

    public static function writeFile(string $reportUrl, string $absolutePath, int $pixelSize = 420): void
    {
        $bytes = self::writeString($reportUrl, $pixelSize);

        if (file_put_contents($absolutePath, $bytes) === false) {
            throw new RuntimeException('Unable to write QR PNG file.');
        }
    }

    public static function writeFileWithWinproxLogo(string $reportUrl, string $absolutePath, int $pixelSize = 420): void
    {
        $bytes = self::writeStringWithWinproxLogo($reportUrl, $pixelSize);

        if (file_put_contents($absolutePath, $bytes) === false) {
            throw new RuntimeException('Unable to write QR PNG file.');
        }
    }

    public static function writeFileForStickerSheet(string $reportUrl, string $absolutePath, int $pixelSize = 420): void
    {
        $bytes = self::writeStringWithWinproxLogo($reportUrl, $pixelSize, self::STICKER_LOGO_BOX_RATIO);

        if (file_put_contents($absolutePath, $bytes) === false) {
            throw new RuntimeException('Unable to write QR PNG file.');
        }
    }

    public static function writeString(string $reportUrl, int $pixelSize = 420): string
    {
        $writer = new Writer(self::renderer($pixelSize));

        return $writer->writeString(
            $reportUrl,
            Encoder::DEFAULT_BYTE_MODE_ENCODING,
            ErrorCorrectionLevel::H(),
        );
    }

    public static function writeStringWithWinproxLogo(string $reportUrl, int $pixelSize = 420, ?float $logoBoxRatio = null): string
    {
        return self::overlayWinproxLogo(
            self::writeString($reportUrl, $pixelSize),
            $pixelSize,
            $logoBoxRatio ?? self::LOGO_BOX_RATIO,
        );
    }

    public static function canGenerate(): bool
    {
        return self::gdRendererAvailable() || self::imagickRendererAvailable();
    }

    private static function renderer(int $pixelSize): RendererInterface
    {
        if (self::gdRendererAvailable()) {
            return new GDLibRenderer(
                $pixelSize,
                self::QUIET_ZONE_MODULES,
                'png',
            );
        }

        if (self::imagickRendererAvailable()) {
            return new ImageRenderer(
                new RendererStyle($pixelSize, self::QUIET_ZONE_MODULES),
                new ImagickImageBackEnd('png'),
            );
        }

        throw new RuntimeException(
            'QR sticker export requires the PHP gd or imagick extension to render PNG codes.',
        );
    }

    private static function overlayWinproxLogo(string $qrPngBytes, int $pixelSize, float $logoBoxRatio = self::LOGO_BOX_RATIO): string
    {
        if (self::gdRendererAvailable()) {
            return self::overlayWinproxLogoWithGd($qrPngBytes, $pixelSize, $logoBoxRatio);
        }

        if (self::imagickRendererAvailable()) {
            return self::overlayWinproxLogoWithImagick($qrPngBytes, $pixelSize, $logoBoxRatio);
        }

        return $qrPngBytes;
    }

    private static function overlayWinproxLogoWithGd(string $qrPngBytes, int $pixelSize, float $logoBoxRatio): string
    {
        $qr = self::loadGdImageFromBinary($qrPngBytes);
        if ($qr === false) {
            throw new RuntimeException('Unable to decode QR PNG for logo overlay.');
        }

        $logoPath = self::winproxLogoPath();
        $logo = self::loadGdImageFromFile($logoPath);
        if ($logo === false) {
            imagedestroy($qr);

            throw new RuntimeException(is_file($logoPath)
                ? 'Unable to load WinProx logo image.'
                : 'WinProx logo image is missing.');
        }

        imagealphablending($qr, true);
        imagesavealpha($qr, true);

        [$boxX, $boxY, $boxSize, $destX, $destY, $targetW, $targetH] = self::logoPlacement($pixelSize, $logoBoxRatio);

        $white = imagecolorallocate($qr, 255, 255, 255);
        $border = imagecolorallocate($qr, 229, 231, 235);
        imagefilledrectangle($qr, $boxX, $boxY, $boxX + $boxSize - 1, $boxY + $boxSize - 1, $white);
        imagerectangle($qr, $boxX, $boxY, $boxX + $boxSize - 1, $boxY + $boxSize - 1, $border);

        imagealphablending($logo, true);
        imagesavealpha($logo, true);
        imagecopyresampled(
            $qr,
            $logo,
            $destX,
            $destY,
            0,
            0,
            $targetW,
            $targetH,
            imagesx($logo),
            imagesy($logo),
        );

        imagedestroy($logo);

        ob_start();
        imagepng($qr);
        imagedestroy($qr);
        $result = ob_get_clean();

        if ($result === false) {
            throw new RuntimeException('Unable to encode QR PNG with logo.');
        }

        return $result;
    }

    private static function overlayWinproxLogoWithImagick(string $qrPngBytes, int $pixelSize, float $logoBoxRatio): string
    {
        $logoPath = self::winproxLogoPath();
        if (! is_file($logoPath)) {
            throw new RuntimeException('WinProx logo image is missing.');
        }

        $qr = new Imagick;
        $qr->readImageBlob($qrPngBytes);

        [$boxX, $boxY, $boxSize, , , $targetW, $targetH] = self::logoPlacement($pixelSize, $logoBoxRatio);

        $background = new Imagick;
        $background->newImage($boxSize, $boxSize, new \ImagickPixel('white'));

        $draw = new \ImagickDraw;
        $draw->setStrokeColor('#e5e7eb');
        $draw->setFillColor('white');
        $draw->setStrokeWidth(1);
        $draw->rectangle(0, 0, $boxSize - 1, $boxSize - 1);
        $background->drawImage($draw);

        $logo = new Imagick;
        $logo->readImage($logoPath);
        $logo->resizeImage($targetW, $targetH, Imagick::FILTER_LANCZOS, 1, true);

        $background->compositeImage(
            $logo,
            Imagick::COMPOSITE_OVER,
            (int) floor(($boxSize - $logo->getImageWidth()) / 2),
            (int) floor(($boxSize - $logo->getImageHeight()) / 2),
        );

        $qr->compositeImage($background, Imagick::COMPOSITE_OVER, $boxX, $boxY);

        $result = $qr->getImageBlob();
        $qr->clear();
        $background->clear();
        $logo->clear();

        return $result;
    }

    /**
     * @return array{int, int, int, int, int, int, int}
     */
    private static function logoPlacement(int $pixelSize, float $logoBoxRatio = self::LOGO_BOX_RATIO): array
    {
        $logoPath = self::winproxLogoPath();
        $logoWidth = 1;
        $logoHeight = 1;

        if (is_file($logoPath)) {
            $info = getimagesize($logoPath);
            if (is_array($info)) {
                $logoWidth = max(1, (int) $info[0]);
                $logoHeight = max(1, (int) $info[1]);
            }
        }

        $boxSize = max(1, (int) round($pixelSize * $logoBoxRatio));
        $padding = max(2, (int) round($boxSize * 0.12));
        $innerSize = max(1, $boxSize - ($padding * 2));
        $boxX = (int) floor(($pixelSize - $boxSize) / 2);
        $boxY = (int) floor(($pixelSize - $boxSize) / 2);
        $scale = min($innerSize / $logoWidth, $innerSize / $logoHeight);
        $targetW = max(1, (int) round($logoWidth * $scale));
        $targetH = max(1, (int) round($logoHeight * $scale));
        $destX = $boxX + (int) floor(($boxSize - $targetW) / 2);
        $destY = $boxY + (int) floor(($boxSize - $targetH) / 2);

        return [$boxX, $boxY, $boxSize, $destX, $destY, $targetW, $targetH];
    }

    private static function winproxLogoPath(): string
    {
        return public_path('images/Winprox_logo_200.png');
    }

    /**
     * GD/libpng may emit benign iCCP warnings on some PNG assets; suppress so production
     * error handlers do not turn them into 500 responses when the image still decodes.
     */
    private static function loadGdImageFromFile(string $absolutePath): \GdImage|false
    {
        if (! is_file($absolutePath)) {
            return false;
        }

        $binary = file_get_contents($absolutePath);

        if ($binary === false || $binary === '') {
            return false;
        }

        return self::loadGdImageFromBinary($binary);
    }

    private static function loadGdImageFromBinary(string $binary): \GdImage|false
    {
        if ($binary === '') {
            return false;
        }

        return @imagecreatefromstring($binary);
    }

    private static function gdRendererAvailable(): bool
    {
        return extension_loaded('gd')
            && function_exists('gd_info')
            && class_exists(GDLibRenderer::class);
    }

    private static function imagickRendererAvailable(): bool
    {
        return extension_loaded('imagick')
            && class_exists(ImagickImageBackEnd::class);
    }
}
