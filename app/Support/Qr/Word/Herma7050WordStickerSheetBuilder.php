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

final class Herma7050WordStickerSheetBuilder
{
    private const QR_IMAGE_MM = 40.0;

    private const CELL_PADDING_TOP_MM = 1.0;

    private const CELL_PADDING_BOTTOM_MM = 1.0;

    private const CELL_PADDING_SIDE_MM = 0.0;

    private const TEXT_GAP_TWIP = 60;

    private const PRIMARY_FONT_PT = 8;

    /**
     * @param  list<QrStickerEntry>  $entries
     */
    public function build(array $entries, ?string $centerLogoPath = null): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(8);

        $perPage = QrStickerSheetTemplate::Herma7050->labelsPerPage();
        $pages = $entries === [] ? [[]] : array_chunk($entries, $perPage);
        $tempFiles = [];

        try {
            foreach ($pages as $pageIndex => $pageEntries) {
                $section = $phpWord->addSection(self::sectionStyle($pageIndex > 0));
                $this->addStickerTable($section, $pageEntries, $tempFiles, $centerLogoPath);
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
        array &$tempFiles,
        ?string $centerLogoPath = null,
    ): void {
        $table = $section->addTable([
            'alignment' => JcTable::START,
            'width' => Herma7050StickerTableLayout::tableWidthTwip(),
            'unit' => 'dxa',
            'layout' => Table::LAYOUT_FIXED,
            'cellMargin' => 0,
        ]);

        $entryIndex = 0;

        for ($rowIndex = 0; $rowIndex < Herma7050StickerTableLayout::ROW_COUNT; $rowIndex++) {
            $table->addRow(Herma7050StickerTableLayout::ROW_TWIP, [
                'exactHeight' => true,
                'cantSplit' => true,
            ]);

            foreach (Herma7050StickerTableLayout::GRID_COLUMN_TWIPS as $columnIndex => $widthTwip) {
                $cell = $table->addCell($widthTwip, self::stickerCellStyle());

                $entry = $pageEntries[$entryIndex] ?? null;
                $entryIndex++;

                if ($entry === null) {
                    continue;
                }

                $pngPath = $this->writeTempQrPng($entry->reportUrl, $tempFiles, $centerLogoPath);
                $cell->addImage($pngPath, [
                    'width' => self::mmToPoint(self::QR_IMAGE_MM),
                    'height' => self::mmToPoint(self::QR_IMAGE_MM),
                    'alignment' => Jc::CENTER,
                    'unit' => 'pt',
                    'marginTop' => 0,
                    'marginBottom' => 0,
                ]);

                $cell->addText($entry->unitLabel, [
                    'bold' => true,
                    'size' => self::PRIMARY_FONT_PT,
                    'color' => '111827',
                ], [
                    'alignment' => Jc::CENTER,
                    'spaceBefore' => self::TEXT_GAP_TWIP,
                    'spaceAfter' => 0,
                ]);
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
            'marginTop' => Herma7050StickerTableLayout::PAGE_MARGIN_TOP_TWIP,
            'marginBottom' => Herma7050StickerTableLayout::PAGE_MARGIN_BOTTOM_TWIP,
            'marginLeft' => Herma7050StickerTableLayout::PAGE_MARGIN_LEFT_TWIP,
            'marginRight' => Herma7050StickerTableLayout::PAGE_MARGIN_RIGHT_TWIP,
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
            WordDocxStickerExportSanitizer::apply($docxPath, QrStickerSheetTemplate::Herma7050);
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
