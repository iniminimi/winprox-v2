<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrPrintablePageBackground;
use App\Support\Qr\QrStickerEntry;
use App\Support\Qr\QrStickerRasterCache;
use App\Support\Qr\QrStickerSheetTemplate;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use InvalidArgumentException;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use RuntimeException;

/**
 * Full-page A6/A5/A4 Word export: one QR centred on page background per page.
 */
final class QrPrintablePageWordBuilder
{
    private const EXPORT_DPI = 200;

    /** QR size as fraction of the page's shorter side. */
    private const QR_SIZE_RATIO = 0.48;

    private const LABEL_GAP_RATIO = 0.03;

    private const LABEL_FONT_RATIO = 0.035;

    /**
     * @param  list<QrStickerEntry>  $entries
     */
    public function build(
        array $entries,
        QrStickerSheetTemplate $template,
        ?string $centerLogoPath = null,
        ?Tenant $tenant = null,
        ?TenantQrStickerSheetSetting $sheetSettings = null,
    ): string {
        if (! $template->isPrintablePage()) {
            throw new InvalidArgumentException('Template is not a printable page layout.');
        }

        if (! QrCodePngWriter::canGenerate()) {
            throw new InvalidArgumentException(
                'QR printable export is unavailable: enable the PHP gd or imagick extension on the server.',
            );
        }

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(10);

        $backgroundPath = QrPrintablePageBackground::absolutePathForTemplate($template, $sheetSettings);
        $pageWidthMm = $template->pageWidthMm();
        $pageHeightMm = $template->pageHeightMm();
        $tempFiles = [];

        try {
            $backgroundPng = $this->prepareBackgroundPng(
                $backgroundPath,
                $pageWidthMm,
                $pageHeightMm,
                $tempFiles,
            );

            foreach ($entries as $pageIndex => $entry) {
                $section = $phpWord->addSection($this->sectionStyle($template, $pageIndex > 0));
                $pagePng = $this->composePagePng(
                    $backgroundPng,
                    $entry,
                    $pageWidthMm,
                    $pageHeightMm,
                    $centerLogoPath,
                    $tempFiles,
                );

                $section->addImage($pagePng, [
                    'width' => self::mmToPoint($pageWidthMm),
                    'height' => self::mmToPoint($pageHeightMm),
                    'unit' => 'pt',
                    'marginTop' => 0,
                    'marginLeft' => 0,
                    'wrappingStyle' => 'inline',
                ]);
            }

            return $this->saveToString($phpWord, $template);
        } finally {
            foreach ($tempFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function prepareBackgroundPng(
        string $backgroundPath,
        float $pageWidthMm,
        float $pageHeightMm,
        array &$tempFiles,
    ): string {
        $widthPx = self::mmToPixelAtDpi($pageWidthMm);
        $heightPx = self::mmToPixelAtDpi($pageHeightMm);

        if (class_exists(Imagick::class)) {
            return $this->rasterizeBackgroundWithImagick($backgroundPath, $widthPx, $heightPx, $tempFiles);
        }

        return $this->rasterizeBackgroundWithGd($backgroundPath, $widthPx, $heightPx, $tempFiles);
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function rasterizeBackgroundWithImagick(
        string $backgroundPath,
        int $widthPx,
        int $heightPx,
        array &$tempFiles,
    ): string {
        try {
            $image = new Imagick;
            $image->setBackgroundColor(new ImagickPixel('white'));
            $image->setResolution(self::EXPORT_DPI, self::EXPORT_DPI);
            $image->readImage($backgroundPath);
            $image->setImageBackgroundColor(new ImagickPixel('white'));
            $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $image->clear();
            $image = $flattened;
            $image->resizeImage($widthPx, $heightPx, Imagick::FILTER_LANCZOS, 1, true);

            $canvas = new Imagick;
            $canvas->newImage($widthPx, $heightPx, new ImagickPixel('white'));
            $canvas->setImageFormat('png');
            $offsetX = (int) round(($widthPx - $image->getImageWidth()) / 2);
            $offsetY = (int) round(($heightPx - $image->getImageHeight()) / 2);
            $canvas->compositeImage($image, Imagick::COMPOSITE_OVER, $offsetX, $offsetY);
            $image->clear();

            $path = $this->allocateTempPng($tempFiles);
            $canvas->writeImage($path);
            $canvas->clear();

            return $path;
        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Unable to rasterize printable QR page background: '.$exception->getMessage(),
                0,
                $exception,
            );
        }
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function rasterizeBackgroundWithGd(
        string $backgroundPath,
        int $widthPx,
        int $heightPx,
        array &$tempFiles,
    ): string {
        $source = QrStickerRasterCache::gdSource($backgroundPath);
        if ($source === false) {
            throw new RuntimeException(
                'Unable to load printable QR page background. Provide a PNG background or enable PHP imagick for SVG.',
            );
        }

        $canvas = imagecreatetruecolor($widthPx, $heightPx);
        if ($canvas === false) {
            throw new RuntimeException('Unable to allocate printable QR page canvas.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        if ($white !== false) {
            imagefilledrectangle($canvas, 0, 0, $widthPx, $heightPx, $white);
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $scale = min($widthPx / $srcW, $heightPx / $srcH);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));
        $offsetX = (int) round(($widthPx - $dstW) / 2);
        $offsetY = (int) round(($heightPx - $dstH) / 2);

        imagecopyresampled($canvas, $source, $offsetX, $offsetY, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $path = $this->allocateTempPng($tempFiles);
        if (! imagepng($canvas, $path)) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to write printable QR page background PNG.');
        }
        imagedestroy($canvas);

        return $path;
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function composePagePng(
        string $backgroundPng,
        QrStickerEntry $entry,
        float $pageWidthMm,
        float $pageHeightMm,
        ?string $centerLogoPath,
        array &$tempFiles,
    ): string {
        if (class_exists(Imagick::class)) {
            return $this->composePageWithImagick(
                $backgroundPng,
                $entry,
                $pageWidthMm,
                $pageHeightMm,
                $centerLogoPath,
                $tempFiles,
            );
        }

        return $this->composePageWithGd(
            $backgroundPng,
            $entry,
            $pageWidthMm,
            $pageHeightMm,
            $centerLogoPath,
            $tempFiles,
        );
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function composePageWithImagick(
        string $backgroundPng,
        QrStickerEntry $entry,
        float $pageWidthMm,
        float $pageHeightMm,
        ?string $centerLogoPath,
        array &$tempFiles,
    ): string {
        $widthPx = self::mmToPixelAtDpi($pageWidthMm);
        $heightPx = self::mmToPixelAtDpi($pageHeightMm);
        [$qrPx, $qrX, $qrY, $labelY, $fontSizePx] = $this->layoutMetrics($pageWidthMm, $pageHeightMm, $entry->unitLabel);

        $qrTemp = $this->allocateTempPng($tempFiles);
        QrCodePngWriter::writeFileForStickerSheet($entry->reportUrl, $qrTemp, max(480, $qrPx), $centerLogoPath);

        $canvas = new Imagick($backgroundPng);
        $qr = new Imagick($qrTemp);
        $qr->resizeImage($qrPx, $qrPx, Imagick::FILTER_LANCZOS, 1, true);
        $canvas->compositeImage($qr, Imagick::COMPOSITE_OVER, $qrX, $qrY);
        $qr->clear();

        if ($entry->unitLabel !== '') {
            $draw = new ImagickDraw;
            $draw->setFillColor(new ImagickPixel('#111827'));
            $draw->setFontSize($fontSizePx);
            $draw->setTextAlignment(Imagick::ALIGN_CENTER);
            $draw->setFontWeight(700);
            $canvas->annotateImage($draw, (int) round($widthPx / 2), $labelY, 0, $entry->unitLabel);
        }

        $canvas->setImageFormat('png');
        $path = $this->allocateTempPng($tempFiles);
        $canvas->writeImage($path);
        $canvas->clear();

        return $path;
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function composePageWithGd(
        string $backgroundPng,
        QrStickerEntry $entry,
        float $pageWidthMm,
        float $pageHeightMm,
        ?string $centerLogoPath,
        array &$tempFiles,
    ): string {
        $widthPx = self::mmToPixelAtDpi($pageWidthMm);
        $heightPx = self::mmToPixelAtDpi($pageHeightMm);
        [$qrPx, $qrX, $qrY, $labelY, $fontSizePx] = $this->layoutMetrics($pageWidthMm, $pageHeightMm, $entry->unitLabel);

        $qrTemp = $this->allocateTempPng($tempFiles);
        QrCodePngWriter::writeFileForStickerSheet($entry->reportUrl, $qrTemp, max(480, $qrPx), $centerLogoPath);

        $canvas = @imagecreatefrompng($backgroundPng);
        if ($canvas === false) {
            throw new RuntimeException('Unable to load printable QR page background PNG.');
        }

        $qr = @imagecreatefrompng($qrTemp);
        if ($qr === false) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to load printable QR PNG.');
        }

        $qrScaled = imagecreatetruecolor($qrPx, $qrPx);
        if ($qrScaled === false) {
            imagedestroy($canvas);
            imagedestroy($qr);
            throw new RuntimeException('Unable to allocate scaled QR canvas.');
        }

        imagealphablending($qrScaled, false);
        imagesavealpha($qrScaled, true);
        imagecopyresampled($qrScaled, $qr, 0, 0, 0, 0, $qrPx, $qrPx, imagesx($qr), imagesy($qr));
        imagedestroy($qr);

        imagealphablending($canvas, true);
        imagecopy($canvas, $qrScaled, $qrX, $qrY, 0, 0, $qrPx, $qrPx);
        imagedestroy($qrScaled);

        if ($entry->unitLabel !== '') {
            $black = imagecolorallocate($canvas, 17, 24, 39);
            if ($black !== false) {
                $bbox = imagettfbbox($fontSizePx, 0, $this->gdFontPath(), $entry->unitLabel);
                $textWidth = is_array($bbox) ? (int) abs($bbox[2] - $bbox[0]) : 0;
                $textX = (int) round(($widthPx - $textWidth) / 2);
                imagettftext($canvas, $fontSizePx, 0, $textX, $labelY, $black, $this->gdFontPath(), $entry->unitLabel);
            }
        }

        $path = $this->allocateTempPng($tempFiles);
        if (! imagepng($canvas, $path)) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to write printable QR page PNG.');
        }
        imagedestroy($canvas);

        return $path;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int} qrPx, qrX, qrY, labelY, fontSizePx
     */
    private function layoutMetrics(float $pageWidthMm, float $pageHeightMm, string $unitLabel): array
    {
        $widthPx = self::mmToPixelAtDpi($pageWidthMm);
        $heightPx = self::mmToPixelAtDpi($pageHeightMm);
        $shortSideMm = min($pageWidthMm, $pageHeightMm);
        $qrMm = $shortSideMm * self::QR_SIZE_RATIO;
        $qrPx = self::mmToPixelAtDpi($qrMm);
        $qrX = (int) round(($widthPx - $qrPx) / 2);
        $labelGapPx = (int) round($heightPx * self::LABEL_GAP_RATIO);
        $fontSizePx = max(14, (int) round($heightPx * self::LABEL_FONT_RATIO));
        $labelReserve = $unitLabel !== '' ? ($labelGapPx + $fontSizePx + 8) : 0;
        $blockHeight = $qrPx + $labelReserve;
        $blockTop = (int) round(($heightPx - $blockHeight) / 2);
        $qrY = $blockTop;
        $labelY = $qrY + $qrPx + $labelGapPx + $fontSizePx;

        return [$qrPx, $qrX, $qrY, $labelY, $fontSizePx];
    }

    private function gdFontPath(): string
    {
        $candidates = [
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('No TrueType font available for printable QR labels.');
    }

    /**
     * @return array<string, float|int|string>
     */
    private function sectionStyle(QrStickerSheetTemplate $template, bool $startsOnNewPage): array
    {
        $style = [
            'pageSizeW' => self::mmToTwip($template->pageWidthMm()),
            'pageSizeH' => self::mmToTwip($template->pageHeightMm()),
            'marginTop' => 0,
            'marginBottom' => 0,
            'marginLeft' => 0,
            'marginRight' => 0,
        ];

        if ($startsOnNewPage) {
            $style['breakType'] = 'nextPage';
        }

        return $style;
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function allocateTempPng(array &$tempFiles): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wp-qr-print-');
        if ($path === false) {
            throw new RuntimeException('Unable to allocate temporary printable QR PNG path.');
        }

        $pngPath = $path.'.png';
        @unlink($path);
        $tempFiles[] = $pngPath;

        return $pngPath;
    }

    private function saveToString(PhpWord $phpWord, QrStickerSheetTemplate $template): string
    {
        $tempDoc = tempnam(sys_get_temp_dir(), 'wp-qr-docx-');
        if ($tempDoc === false) {
            throw new RuntimeException('Unable to allocate temporary DOCX path.');
        }

        $docxPath = $tempDoc.'.docx';
        @unlink($tempDoc);

        try {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($docxPath);
            WordDocxStickerExportSanitizer::apply($docxPath, $template);
            $binary = file_get_contents($docxPath);

            if ($binary === false) {
                throw new RuntimeException('Unable to read generated DOCX.');
            }

            return $binary;
        } finally {
            if (is_file($docxPath)) {
                @unlink($docxPath);
            }
        }
    }

    private static function mmToTwip(float $millimeters): int
    {
        return (int) round(Converter::cmToTwip($millimeters / 10));
    }

    private static function mmToPoint(float $millimeters): float
    {
        return Converter::cmToPoint($millimeters / 10);
    }

    private static function mmToPixelAtDpi(float $millimeters): int
    {
        return (int) max(1, round(($millimeters / 25.4) * self::EXPORT_DPI));
    }
}
