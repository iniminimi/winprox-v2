<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

/**
 * Avery 62×89-R Word table grid (from tests/Avery_62x89-R.docx).
 */
final class Avery62x89StickerTableLayout
{
    /** @var list<int> */
    public const GRID_COLUMN_TWIPS = [3514, 283, 3514, 283, 3517];

    public const LABEL_ROW_TWIP = 5045;

    public const GAP_ROW_TWIP = 283;

    public const ROW_COUNT = 5;

    /** @var list<int> */
    public const LABEL_COLUMN_INDEXES = [0, 2, 4];

    /** @var list<int> */
    public const LABEL_ROW_INDEXES = [0, 2, 4];

    public const PAGE_MARGIN_TOP_TWIP = 566;

    public const PAGE_MARGIN_RIGHT_TWIP = 446;

    public const PAGE_MARGIN_BOTTOM_TWIP = 446;

    public const PAGE_MARGIN_LEFT_TWIP = 522;

    public static function tableWidthTwip(): int
    {
        return array_sum(self::GRID_COLUMN_TWIPS);
    }

    public static function rowHeightTwip(int $rowIndex): int
    {
        return in_array($rowIndex, self::LABEL_ROW_INDEXES, true)
            ? self::LABEL_ROW_TWIP
            : self::GAP_ROW_TWIP;
    }

    public static function isLabelRow(int $rowIndex): bool
    {
        return in_array($rowIndex, self::LABEL_ROW_INDEXES, true);
    }

    public static function tblPrXml(): string
    {
        return '<w:tblPr>'
            .'<w:tblW w:w="0" w:type="auto"/>'
            .'<w:tblLayout w:type="fixed"/>'
            .'<w:tblCellMar>'
            .'<w:left w:w="115" w:type="dxa"/>'
            .'<w:right w:w="115" w:type="dxa"/>'
            .'</w:tblCellMar>'
            .'<w:tblLook w:val="0000" w:firstRow="0" w:lastRow="0" w:firstColumn="0" w:lastColumn="0" w:noHBand="0" w:noVBand="0"/>'
            .'</w:tblPr>';
    }

    public static function tblGridXml(): string
    {
        $columns = '';
        foreach (self::GRID_COLUMN_TWIPS as $width) {
            $columns .= '<w:gridCol w:w="'.$width.'"/>';
        }

        return '<w:tblGrid>'.$columns.'</w:tblGrid>';
    }

    public static function patchDocument(string $documentXml): string
    {
        $documentXml = preg_replace(
            '/<w:tbl>\s*<w:tblGrid>.*?<\/w:tblGrid>\s*<w:tblPr>.*?<\/w:tblPr>/s',
            '<w:tbl>'.self::tblPrXml().self::tblGridXml(),
            $documentXml,
        ) ?? $documentXml;

        $documentXml = preg_replace('/<w:tblPr\b[^>]*>.*?<\/w:tblPr>/s', self::tblPrXml(), $documentXml) ?? $documentXml;
        $documentXml = preg_replace('/<w:tblGrid>.*?<\/w:tblGrid>/s', self::tblGridXml(), $documentXml) ?? $documentXml;

        $pgMar = sprintf(
            '<w:pgMar w:top="%d" w:right="%d" w:bottom="%d" w:left="%d" w:header="0" w:footer="0" w:gutter="0"/>',
            self::PAGE_MARGIN_TOP_TWIP,
            self::PAGE_MARGIN_RIGHT_TWIP,
            self::PAGE_MARGIN_BOTTOM_TWIP,
            self::PAGE_MARGIN_LEFT_TWIP,
        );

        $documentXml = preg_replace('/<w:pgMar\b[^>]*\/>/', $pgMar, $documentXml) ?? $documentXml;

        return self::ensureLabelCellsVerticallyCentered($documentXml);
    }

    private static function ensureLabelCellsVerticallyCentered(string $documentXml): string
    {
        return preg_replace_callback(
            '/<w:tcPr>(.*?)<\/w:tcPr>/s',
            static function (array $matches): string {
                $tcPr = $matches[1];
                if (str_contains($tcPr, 'w:vAlign')) {
                    return '<w:tcPr>'.$tcPr.'</w:tcPr>';
                }

                return '<w:tcPr>'.$tcPr.'<w:vAlign w:val="center"/></w:tcPr>';
            },
            $documentXml,
        ) ?? $documentXml;
    }
}
