<?php

declare(strict_types=1);

namespace App\Support\Qr\Word;

/**
 * HERMA 70×50 mm Word table grid (from tests/HERMA-70x50-8-15st.docx).
 */
final class Herma7050StickerTableLayout
{
    /** @var list<int> */
    public const GRID_COLUMN_TWIPS = [3968, 3968, 3968];

    public const ROW_TWIP = 2880;

    public const ROW_COUNT = 5;

    /** @var list<int> */
    public const LABEL_COLUMN_INDEXES = [0, 1, 2];

    public const PAGE_MARGIN_TOP_TWIP = 1219;

    public const PAGE_MARGIN_RIGHT_TWIP = 0;

    public const PAGE_MARGIN_BOTTOM_TWIP = 0;

    public const PAGE_MARGIN_LEFT_TWIP = 0;

    public static function tableWidthTwip(): int
    {
        return array_sum(self::GRID_COLUMN_TWIPS);
    }

    public static function tblPrXml(): string
    {
        return '<w:tblPr>'
            .'<w:tblW w:w="0" w:type="auto"/>'
            .'<w:tblLayout w:type="fixed"/>'
            .'<w:tblCellMar>'
            .'<w:left w:w="15" w:type="dxa"/>'
            .'<w:right w:w="15" w:type="dxa"/>'
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
            '<w:pgMar w:top="%d" w:right="%d" w:bottom="%d" w:left="%d" w:header="720" w:footer="720" w:gutter="0"/>',
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
