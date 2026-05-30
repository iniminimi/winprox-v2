<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerEntry;
use App\Support\Qr\QrStickerSheetTemplate;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table;

final class Avery55x55WordStickerSheetBuilder
{
    private const MARGIN_TOP_MM = 10.99;

    private const MARGIN_BOTTOM_MM = 8.87;

    private const MARGIN_LEFT_MM = 19.7;

    private const MARGIN_RIGHT_MM = 7.87;

    private const STICKER_MM = 55.0;

    private const GUTTER_MM = 4.99;

    private const QR_IMAGE_MM = 35.0;

    private const CELL_PADDING_TOP_MM = 3.0;

    private const CELL_PADDING_BOTTOM_MM = 3.0;

    private const CELL_PADDING_SIDE_MM = 2.5;

    private const TEXT_GAP_TWIP = 100;

    private const HEADLINE_FONT_PT = 9;

    private const PRIMARY_FONT_PT = 8;

    private const SECONDARY_FONT_PT = 7;

    /** @var list<int> */
    private const LABEL_COLUMN_INDEXES = [0, 2, 4];

    /**
     * @param  list<QrStickerEntry>  $entries
     */
    public function build(array $entries, string $headline, ?string $centerLogoPath = null): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(8);

        $perPage = QrStickerSheetTemplate::Avery55x55S->labelsPerPage();
        $pages = $entries === [] ? [[]] : array_chunk($entries, $perPage);
        $tempFiles = [];

        try {
            foreach ($pages as $pageIndex => $pageEntries) {
                $section = $phpWord->addSection(self::sectionStyle($pageIndex > 0));
                $this->addStickerTable($section, $pageEntries, $headline, $tempFiles, $centerLogoPath);
            }

            return $this->saveToString($phpWord);
        } finally {
            foreach ($tempFiles as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /**
     * @param  list<QrStickerEntry>  $pageEntries
     * @param  list<string>  $tempFiles
     */
    private function addStickerTable(
        \PhpOffice\PhpWord\Element\Section $section,
        array $pageEntries,
        string $headline,
        array &$tempFiles,
        ?string $centerLogoPath = null,
    ): void {
    {
        $table = $section->addTable([
            'alignment' => JcTable::START,
            'width' => 100 * 50,
            'unit' => 'pct',
            'layout' => Table::LAYOUT_FIXED,
            'cellMargin' => 0,
        ]);

        $entryIndex = 0;

        for ($rowIndex = 0; $rowIndex < 5; $rowIndex++) {
            $table->addRow(self::mmToTwip(self::STICKER_MM), [
                'exactHeight' => true,
                'cantSplit' => true,
            ]);

            for ($columnIndex = 0; $columnIndex < 5; $columnIndex++) {
                $isLabelColumn = in_array($columnIndex, self::LABEL_COLUMN_INDEXES, true);
                $widthMm = $isLabelColumn ? self::STICKER_MM : self::GUTTER_MM;
                $cell = $table->addCell(self::mmToTwip($widthMm), self::stickerCellStyle());

                if (! $isLabelColumn) {
                    continue;
                }

                $entry = $pageEntries[$entryIndex] ?? null;
                $entryIndex++;

                if ($entry === null) {
                    continue;
                }

                $cell->addText($headline, [
                    'bold' => true,
                    'size' => self::HEADLINE_FONT_PT,
                    'color' => '111827',
                ], [
                    'alignment' => Jc::CENTER,
                    'spaceAfter' => self::TEXT_GAP_TWIP,
                    'spaceBefore' => 0,
                ]);

                $pngPath = $this->writeTempQrPng($entry->reportUrl, $tempFiles, $centerLogoPath);
                $cell->addImage($pngPath, [
                    'width' => self::mmToPoint(self::QR_IMAGE_MM),
                    'height' => self::mmToPoint(self::QR_IMAGE_MM),
                    'alignment' => Jc::CENTER,
                    'unit' => 'pt',
                    'marginTop' => self::mmToPoint(1.0),
                    'marginBottom' => self::mmToPoint(1.0),
                ]);

                $cell->addText($entry->primaryLabel, [
                    'bold' => true,
                    'size' => self::PRIMARY_FONT_PT,
                    'color' => '111827',
                ], [
                    'alignment' => Jc::CENTER,
                    'spaceBefore' => self::TEXT_GAP_TWIP,
                    'spaceAfter' => 0,
                ]);

                if (trim($entry->secondaryLabel) !== '') {
                    $cell->addText($entry->secondaryLabel, [
                        'bold' => true,
                        'size' => self::SECONDARY_FONT_PT,
                        'color' => '111827',
                    ], [
                        'alignment' => Jc::CENTER,
                        'spaceBefore' => 40,
                        'spaceAfter' => 0,
                    ]);
                }
            }
        }
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function writeTempQrPng(string $reportUrl, array &$tempFiles, ?string $centerLogoPath = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wp-qr-');
        if ($path === false) {
            throw new \RuntimeException('Unable to allocate temporary QR PNG path.');
        }

        $pngPath = $path.'.png';
        @unlink($path);
        $renderPx = max(480, self::mmToPixel(self::QR_IMAGE_MM) * 3);
        QrCodePngWriter::writeFileForStickerSheet($reportUrl, $pngPath, $renderPx, $centerLogoPath);
        $tempFiles[] = $pngPath;

        return $pngPath;
    }

    private static function mmToTwip(float $millimeters): int
    {
        return (int) round(Converter::cmToTwip($millimeters / 10));
    }

    private static function mmToPoint(float $millimeters): float
    {
        return Converter::cmToPoint($millimeters / 10);
    }

    private static function mmToPixel(float $millimeters): int
    {
        return (int) round(Converter::cmToPixel($millimeters / 10));
    }

    /**
     * @return array<string, int|string>
     */
    private static function sectionStyle(bool $startsOnNewPage): array
    {
        $style = [
            'pageSizeW' => Converter::inchToTwip(8.27),
            'pageSizeH' => Converter::inchToTwip(11.69),
            'marginTop' => self::mmToTwip(self::MARGIN_TOP_MM),
            'marginBottom' => self::mmToTwip(self::MARGIN_BOTTOM_MM),
            'marginLeft' => self::mmToTwip(self::MARGIN_LEFT_MM),
            'marginRight' => self::mmToTwip(self::MARGIN_RIGHT_MM),
        ];

        if ($startsOnNewPage) {
            $style['breakType'] = 'nextPage';
        }

        return $style;
    }

    /**
     * @return array<string, int|string>
     */
    private static function stickerCellStyle(): array
    {
        return [
            'valign' => 'center',
            'marginTop' => self::mmToTwip(self::CELL_PADDING_TOP_MM),
            'marginBottom' => self::mmToTwip(self::CELL_PADDING_BOTTOM_MM),
            'marginLeft' => self::mmToTwip(self::CELL_PADDING_SIDE_MM),
            'marginRight' => self::mmToTwip(self::CELL_PADDING_SIDE_MM),
        ];
    }

    private function saveToString(PhpWord $phpWord): string
    {
        $tempDoc = tempnam(sys_get_temp_dir(), 'wp-qr-docx-');
        if ($tempDoc === false) {
            throw new \RuntimeException('Unable to allocate temporary DOCX path.');
        }

        $docxPath = $tempDoc.'.docx';
        @unlink($tempDoc);

        try {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save($docxPath);
            WordDocxStickerExportSanitizer::apply($docxPath);
            $binary = file_get_contents($docxPath);

            if ($binary === false) {
                throw new \RuntimeException('Unable to read generated DOCX.');
            }

            return $binary;
        } finally {
            if (is_file($docxPath)) {
                @unlink($docxPath);
            }
        }
    }
}
