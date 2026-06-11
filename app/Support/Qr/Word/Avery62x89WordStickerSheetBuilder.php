<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

use App\Models\Tenant;
use App\Support\Qr\BrandedQrStickerCompositor;
use App\Support\Qr\BrandedQrStickerHeaderText;
use App\Support\Qr\QrStickerBackground;
use App\Support\Qr\QrStickerEntry;
use App\Support\Qr\QrStickerSheetTemplate;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Table;

final class Avery62x89WordStickerSheetBuilder
{
    private const STICKER_WIDTH_MM = 62.0;

    private const STICKER_HEIGHT_MM = 89.0;

    public function __construct(
        private readonly BrandedQrStickerCompositor $compositor = new BrandedQrStickerCompositor,
    ) {}

    /**
     * @param  list<QrStickerEntry>  $entries
     */
    public function build(array $entries, ?string $centerLogoPath = null, ?Tenant $tenant = null): string
    {
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(8);

        $perPage = QrStickerSheetTemplate::Avery62x89R->labelsPerPage();
        $pages = $entries === [] ? [[]] : array_chunk($entries, $perPage);
        $tempFiles = [];
        $backgroundPath = QrStickerBackground::defaultAvery62x89AbsolutePath();

        try {
            foreach ($pages as $pageIndex => $pageEntries) {
                $section = $phpWord->addSection(self::sectionStyle($pageIndex > 0));
                $this->addStickerTable($section, $pageEntries, $tempFiles, $backgroundPath, $centerLogoPath, $tenant);
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
        string $backgroundPath,
        ?string $centerLogoPath = null,
        ?Tenant $tenant = null,
    ): void {
        $table = $section->addTable([
            'alignment' => JcTable::START,
            'width' => Avery62x89StickerTableLayout::tableWidthTwip(),
            'unit' => 'dxa',
            'layout' => Table::LAYOUT_FIXED,
            'cellMargin' => 0,
        ]);

        $entryIndex = 0;

        for ($rowIndex = 0; $rowIndex < Avery62x89StickerTableLayout::ROW_COUNT; $rowIndex++) {
            $table->addRow(Avery62x89StickerTableLayout::rowHeightTwip($rowIndex), [
                'exactHeight' => true,
                'cantSplit' => true,
            ]);

            if (! Avery62x89StickerTableLayout::isLabelRow($rowIndex)) {
                $table->addCell(Avery62x89StickerTableLayout::tableWidthTwip(), [
                    'gridSpan' => count(Avery62x89StickerTableLayout::GRID_COLUMN_TWIPS),
                    'valign' => 'center',
                ]);

                continue;
            }

            for ($columnIndex = 0; $columnIndex < count(Avery62x89StickerTableLayout::GRID_COLUMN_TWIPS); $columnIndex++) {
                $isLabelColumn = in_array($columnIndex, Avery62x89StickerTableLayout::LABEL_COLUMN_INDEXES, true);
                $widthTwip = Avery62x89StickerTableLayout::GRID_COLUMN_TWIPS[$columnIndex];
                $cell = $table->addCell($widthTwip, self::stickerCellStyle());

                if (! $isLabelColumn) {
                    continue;
                }

                $entry = $pageEntries[$entryIndex] ?? null;
                $entryIndex++;

                if ($entry === null) {
                    continue;
                }

                $pngPath = $this->writeTempCompositePng(
                    $entry,
                    $backgroundPath,
                    $tempFiles,
                    $centerLogoPath,
                    $tenant,
                );

                $cell->addImage($pngPath, [
                    'width' => self::mmToPoint(self::STICKER_WIDTH_MM),
                    'height' => self::mmToPoint(self::STICKER_HEIGHT_MM),
                    'alignment' => Jc::CENTER,
                    'unit' => 'pt',
                    'marginTop' => 0,
                    'marginBottom' => 0,
                ]);
            }
        }
    }

    /**
     * @param  list<string>  $tempFiles
     */
    private function writeTempCompositePng(
        QrStickerEntry $entry,
        string $backgroundPath,
        array &$tempFiles,
        ?string $centerLogoPath = null,
        ?Tenant $tenant = null,
    ): string {
        $path = tempnam(sys_get_temp_dir(), 'wp-branded-sticker-');
        if ($path === false) {
            throw new \RuntimeException('Unable to allocate temporary branded sticker PNG path.');
        }

        $pngPath = $path.'.png';
        @unlink($path);

        $this->compositor->writeFile(
            $backgroundPath,
            $entry->reportUrl,
            $pngPath,
            $centerLogoPath,
            BrandedQrStickerHeaderText::resolve($tenant, $entry->headerFallback),
            self::footerLabel($entry),
        );
        $tempFiles[] = $pngPath;

        return $pngPath;
    }

    private static function footerLabel(QrStickerEntry $entry): ?string
    {
        $stickerNumber = trim((string) ($entry->stickerNumber ?? ''));

        return $stickerNumber !== '' ? $stickerNumber : null;
    }

    private static function mmToTwip(float $millimeters): int
    {
        return (int) round(Converter::cmToTwip($millimeters / 10));
    }

    private static function mmToPoint(float $millimeters): float
    {
        return Converter::cmToPoint($millimeters / 10);
    }

    /**
     * @return array<string, int|string>
     */
    private static function sectionStyle(bool $startsOnNewPage): array
    {
        $style = [
            'pageSizeW' => Converter::inchToTwip(8.27),
            'pageSizeH' => Converter::inchToTwip(11.69),
            'marginTop' => Avery62x89StickerTableLayout::PAGE_MARGIN_TOP_TWIP,
            'marginBottom' => Avery62x89StickerTableLayout::PAGE_MARGIN_BOTTOM_TWIP,
            'marginLeft' => Avery62x89StickerTableLayout::PAGE_MARGIN_LEFT_TWIP,
            'marginRight' => Avery62x89StickerTableLayout::PAGE_MARGIN_RIGHT_TWIP,
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
            'marginTop' => 0,
            'marginBottom' => 0,
            'marginLeft' => 0,
            'marginRight' => 0,
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
            WordDocxStickerExportSanitizer::apply($docxPath, QrStickerSheetTemplate::Avery62x89R);
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
