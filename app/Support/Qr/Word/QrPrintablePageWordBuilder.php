<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\BrandedQrStickerLayoutConfig;
use App\Support\Qr\BrandedQrStickerTenantDetails;
use App\Support\Qr\QrCenterLogo;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrPrintablePageBackground;
use App\Support\Qr\QrPrintablePageFont;
use App\Support\Qr\QrPrintablePageTenantBranding;
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

    /** Safe printer margin on each side (avoids edge clipping). */
    private const PAGE_MARGIN_MM = 5.0;

    /** QR size as fraction of the printable area's shorter side. */
    private const QR_SIZE_RATIO = 0.48;

    private const LABEL_GAP_MM = 2.5;

    /** Space between location caption (above) and the QR code. */
    private const ABOVE_QR_GAP_MM = 6.0;

    /** Space between optional page headline and the caption above the QR. */
    private const HEADLINE_GAP_MM = 4.0;

    /** Type sizes at A6 content width; scaled up for A5/A4. */
    private const PRIMARY_FONT_MM = 2.8;

    private const SECONDARY_FONT_MM = 3.8;

    private const HEADLINE_FONT_MM = 5.2;

    /** A6 printable content short side (105 − 2×5 mm margins). */
    private const BASE_CONTENT_SHORT_SIDE_MM = 95.0;

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
        $contentWidthMm = $pageWidthMm - (2 * self::PAGE_MARGIN_MM);
        $contentHeightMm = $pageHeightMm - (2 * self::PAGE_MARGIN_MM);
        $tempFiles = [];

        try {
            $backgroundPng = $this->prepareBackgroundPng(
                $backgroundPath,
                $contentWidthMm,
                $contentHeightMm,
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
                    $tenant,
                    $sheetSettings,
                    $tempFiles,
                );

                $section->addImage($pagePng, [
                    'width' => self::mmToPoint($contentWidthMm),
                    'height' => self::mmToPoint($contentHeightMm),
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
        float $contentWidthMm,
        float $contentHeightMm,
        array &$tempFiles,
    ): string {
        $widthPx = self::mmToPixelAtDpi($contentWidthMm);
        $heightPx = self::mmToPixelAtDpi($contentHeightMm);

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
        ?Tenant $tenant,
        ?TenantQrStickerSheetSetting $sheetSettings,
        array &$tempFiles,
    ): string {
        if (class_exists(Imagick::class)) {
            return $this->composePageWithImagick(
                $backgroundPng,
                $entry,
                $pageWidthMm,
                $pageHeightMm,
                $centerLogoPath,
                $tenant,
                $sheetSettings,
                $tempFiles,
            );
        }

        return $this->composePageWithGd(
            $backgroundPng,
            $entry,
            $pageWidthMm,
            $pageHeightMm,
            $centerLogoPath,
            $tenant,
            $sheetSettings,
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
        ?Tenant $tenant,
        ?TenantQrStickerSheetSetting $sheetSettings,
        array &$tempFiles,
    ): string {
        $contentWidthMm = $pageWidthMm - (2 * self::PAGE_MARGIN_MM);
        $contentHeightMm = $pageHeightMm - (2 * self::PAGE_MARGIN_MM);
        $widthPx = self::mmToPixelAtDpi($contentWidthMm);
        $layout = $this->layoutMetrics($contentWidthMm, $contentHeightMm, $entry);

        $qrTemp = $this->allocateTempPng($tempFiles);
        QrCodePngWriter::writeFileForStickerSheet(
            $entry->reportUrl,
            $qrTemp,
            max(480, $layout['qrPx']),
            $centerLogoPath,
        );

        $canvas = new Imagick($backgroundPng);
        $qr = new Imagick($qrTemp);
        $qr->resizeImage($layout['qrPx'], $layout['qrPx'], Imagick::FILTER_LANCZOS, 1, true);
        $canvas->compositeImage($qr, Imagick::COMPOSITE_OVER, $layout['qrX'], $layout['qrY']);
        $qr->clear();

        $centerX = (int) round($widthPx / 2);
        if ($layout['headlineText'] !== '') {
            $this->drawImagickCenteredText(
                $canvas,
                $layout['headlineText'],
                $centerX,
                $layout['headlineY'],
                $layout['headlineFontPx'],
                QrPrintablePageFont::semiboldAbsolutePath(),
            );
        }
        if ($layout['primaryText'] !== '') {
            $this->drawImagickCenteredText(
                $canvas,
                $layout['primaryText'],
                $centerX,
                $layout['primaryY'],
                $layout['primaryFontPx'],
                QrPrintablePageFont::semiboldAbsolutePath(),
            );
        }
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

        $this->drawTenantBrandingOnImagick($canvas, $tenant, $sheetSettings);

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
        ?Tenant $tenant,
        ?TenantQrStickerSheetSetting $sheetSettings,
        array &$tempFiles,
    ): string {
        $contentWidthMm = $pageWidthMm - (2 * self::PAGE_MARGIN_MM);
        $contentHeightMm = $pageHeightMm - (2 * self::PAGE_MARGIN_MM);
        $widthPx = self::mmToPixelAtDpi($contentWidthMm);
        $layout = $this->layoutMetrics($contentWidthMm, $contentHeightMm, $entry);

        $qrTemp = $this->allocateTempPng($tempFiles);
        QrCodePngWriter::writeFileForStickerSheet(
            $entry->reportUrl,
            $qrTemp,
            max(480, $layout['qrPx']),
            $centerLogoPath,
        );

        $canvas = @imagecreatefrompng($backgroundPng);
        if ($canvas === false) {
            throw new RuntimeException('Unable to load printable QR page background PNG.');
        }

        $qr = @imagecreatefrompng($qrTemp);
        if ($qr === false) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to load printable QR PNG.');
        }

        $qrScaled = imagecreatetruecolor($layout['qrPx'], $layout['qrPx']);
        if ($qrScaled === false) {
            imagedestroy($canvas);
            imagedestroy($qr);
            throw new RuntimeException('Unable to allocate scaled QR canvas.');
        }

        imagealphablending($qrScaled, false);
        imagesavealpha($qrScaled, true);
        imagecopyresampled($qrScaled, $qr, 0, 0, 0, 0, $layout['qrPx'], $layout['qrPx'], imagesx($qr), imagesy($qr));
        imagedestroy($qr);

        imagealphablending($canvas, true);
        imagecopy($canvas, $qrScaled, $layout['qrX'], $layout['qrY'], 0, 0, $layout['qrPx'], $layout['qrPx']);
        imagedestroy($qrScaled);

        $color = imagecolorallocate($canvas, 17, 24, 39);
        if ($color !== false) {
            if ($layout['headlineText'] !== '') {
                $this->drawGdCenteredText(
                    $canvas,
                    $layout['headlineText'],
                    $widthPx,
                    $layout['headlineY'],
                    $layout['headlineFontPx'],
                    $color,
                    QrPrintablePageFont::semiboldAbsolutePath(),
                );
            }
            if ($layout['primaryText'] !== '') {
                $this->drawGdCenteredText(
                    $canvas,
                    $layout['primaryText'],
                    $widthPx,
                    $layout['primaryY'],
                    $layout['primaryFontPx'],
                    $color,
                    QrPrintablePageFont::semiboldAbsolutePath(),
                );
            }
            if ($layout['secondaryText'] !== '') {
                $this->drawGdCenteredText(
                    $canvas,
                    $layout['secondaryText'],
                    $widthPx,
                    $layout['secondaryY'],
                    $layout['secondaryFontPx'],
                    $color,
                    QrPrintablePageFont::regularAbsolutePath(),
                );
            }
        }

        $this->drawTenantBrandingOnGd($canvas, $tenant, $sheetSettings);

        $path = $this->allocateTempPng($tempFiles);
        if (! imagepng($canvas, $path)) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to write printable QR page PNG.');
        }
        imagedestroy($canvas);

        return $path;
    }

    private function drawTenantBrandingOnImagick(
        Imagick $canvas,
        ?Tenant $tenant,
        ?TenantQrStickerSheetSetting $sheetSettings,
    ): void {
        $layout = BrandedQrStickerLayoutConfig::fromSetting($sheetSettings);
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
    private function drawTenantBrandingOnGd(
        $canvas,
        ?Tenant $tenant,
        ?TenantQrStickerSheetSetting $sheetSettings,
    ): void {
        $layout = BrandedQrStickerLayoutConfig::fromSetting($sheetSettings);
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

    /**
     * @return array{
     *     qrPx: int,
     *     qrX: int,
     *     qrY: int,
     *     primaryText: string,
     *     secondaryText: string,
     *     headlineText: string,
     *     primaryY: int,
     *     secondaryY: int,
     *     headlineY: int,
     *     primaryFontPx: float,
     *     secondaryFontPx: float,
     *     headlineFontPx: float
     * }
     */
    private function layoutMetrics(float $contentWidthMm, float $contentHeightMm, QrStickerEntry $entry): array
    {
        $widthPx = self::mmToPixelAtDpi($contentWidthMm);
        $heightPx = self::mmToPixelAtDpi($contentHeightMm);
        $shortSideMm = min($contentWidthMm, $contentHeightMm);
        $qrMm = $shortSideMm * self::QR_SIZE_RATIO;
        $qrPx = self::mmToPixelAtDpi($qrMm);
        $qrX = (int) round(($widthPx - $qrPx) / 2);

        $primaryText = trim((string) ($entry->stickerNumber ?? $entry->unitLabel));
        $secondaryText = trim((string) ($entry->locationUnitLabel ?? ''));
        $headlineText = trim((string) ($entry->pageHeadline ?? ''));
        // Avoid duplicate lines when sticker number equals the location/unit caption.
        if ($secondaryText !== '' && strcasecmp($secondaryText, $primaryText) === 0) {
            $secondaryText = '';
        }
        if ($headlineText !== '' && strcasecmp($headlineText, $secondaryText) === 0) {
            $headlineText = '';
        }

        $typeScale = min($contentWidthMm, $contentHeightMm) / self::BASE_CONTENT_SHORT_SIDE_MM;
        $primaryFontPx = self::mmToPixelAtDpi(self::PRIMARY_FONT_MM * $typeScale);
        $secondaryFontPx = self::mmToPixelAtDpi(self::SECONDARY_FONT_MM * $typeScale);
        $headlineFontPx = self::mmToPixelAtDpi(self::HEADLINE_FONT_MM * $typeScale);
        $labelGapPx = self::mmToPixelAtDpi(self::LABEL_GAP_MM * $typeScale);
        $aboveQrGapPx = self::mmToPixelAtDpi(self::ABOVE_QR_GAP_MM * $typeScale);
        $headlineGapPx = self::mmToPixelAtDpi(self::HEADLINE_GAP_MM * $typeScale);

        $aboveBlock = 0;
        if ($headlineText !== '') {
            $aboveBlock += (int) ceil($headlineFontPx) + 2;
            if ($secondaryText !== '') {
                $aboveBlock += $headlineGapPx;
            } else {
                $aboveBlock += $aboveQrGapPx;
            }
        }
        if ($secondaryText !== '') {
            $aboveBlock += (int) ceil($secondaryFontPx) + 2 + $aboveQrGapPx;
        }

        $belowBlock = 0;
        if ($primaryText !== '') {
            $belowBlock = $labelGapPx + (int) ceil($primaryFontPx) + 2;
        }

        $blockHeight = $aboveBlock + $qrPx + $belowBlock;
        $blockTop = (int) round(($heightPx - $blockHeight) / 2);

        $cursorY = $blockTop;
        $headlineY = 0;
        $secondaryY = 0;

        if ($headlineText !== '') {
            $headlineY = $cursorY + (int) round($headlineFontPx);
            $cursorY = $headlineY + ($secondaryText !== '' ? $headlineGapPx : $aboveQrGapPx);
        }

        $qrY = $cursorY;
        if ($secondaryText !== '') {
            $secondaryY = $cursorY + (int) round($secondaryFontPx);
            $qrY = $secondaryY + $aboveQrGapPx;
        }

        $primaryY = $qrY + $qrPx + $labelGapPx + (int) round($primaryFontPx);

        return [
            'qrPx' => $qrPx,
            'qrX' => $qrX,
            'qrY' => $qrY,
            'primaryText' => $primaryText,
            'secondaryText' => $secondaryText,
            'headlineText' => $headlineText,
            'primaryY' => $primaryY,
            'secondaryY' => $secondaryY,
            'headlineY' => $headlineY,
            'primaryFontPx' => (float) $primaryFontPx,
            'secondaryFontPx' => (float) $secondaryFontPx,
            'headlineFontPx' => (float) $headlineFontPx,
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
        $draw = new ImagickDraw;
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

    /**
     * @return array<string, float|int|string>
     */
    private function sectionStyle(QrStickerSheetTemplate $template, bool $startsOnNewPage): array
    {
        $marginTwip = self::mmToTwip(self::PAGE_MARGIN_MM);
        $style = [
            'pageSizeW' => self::mmToTwip($template->pageWidthMm()),
            'pageSizeH' => self::mmToTwip($template->pageHeightMm()),
            'marginTop' => $marginTwip,
            'marginBottom' => $marginTwip,
            'marginLeft' => $marginTwip,
            'marginRight' => $marginTwip,
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
