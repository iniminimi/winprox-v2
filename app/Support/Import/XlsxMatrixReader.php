<?php

declare(strict_types=1);

namespace App\Support\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the first worksheet of an .xlsx file as a dense matrix of string cells.
 * Same lightweight ZipArchive approach as marketing spreadsheet readers (no PhpSpreadsheet).
 */
final class XlsxMatrixReader
{
    /**
     * @return list<list<string>>
     */
    public function read(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Spreadsheet not found: {$absolutePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException("Unable to open spreadsheet: {$absolutePath}");
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new RuntimeException('Worksheet sheet1.xml not found in spreadsheet.');
            }

            $sheet = new SimpleXMLElement($sheetXml);
            $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $matrix = [];
            foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $row) {
                $row->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $cells = $this->readRowCells($row, $sharedStrings);
                if ($cells === []) {
                    continue;
                }

                $matrix[] = $this->toDenseRow($cells);
            }

            return $matrix;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return list<string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $document = new SimpleXMLElement($xml);
        $document->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($document->xpath('//m:si') ?: [] as $sharedItem) {
            $sharedItem->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $parts = $sharedItem->xpath('.//m:t') ?: [];
            $strings[] = implode('', array_map(static fn (SimpleXMLElement $node): string => (string) $node, $parts));
        }

        return $strings;
    }

    /**
     * @return array<string, string> column letter => value
     */
    private function readRowCells(SimpleXMLElement $row, array $sharedStrings): array
    {
        $cells = [];

        foreach ($row->xpath('m:c') ?: [] as $cell) {
            $cell->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $reference = (string) $cell['r'];
            $column = preg_replace('/\d+/', '', $reference) ?? '';
            if ($column === '') {
                continue;
            }

            $type = (string) $cell['t'];
            if ($type === 'inlineStr') {
                $textNodes = $cell->xpath('.//m:t') ?: [];
                $cells[$column] = implode('', array_map(static fn (SimpleXMLElement $node): string => (string) $node, $textNodes));

                continue;
            }

            $valueNode = $cell->xpath('m:v') ?: [];
            if ($valueNode === []) {
                $cells[$column] = '';

                continue;
            }

            $raw = (string) $valueNode[0];
            $cells[$column] = $type === 's'
                ? ($sharedStrings[(int) $raw] ?? '')
                : $raw;
        }

        return $cells;
    }

    /**
     * @param  array<string, string>  $cells
     * @return list<string>
     */
    private function toDenseRow(array $cells): array
    {
        $maxIndex = 0;
        foreach (array_keys($cells) as $column) {
            $maxIndex = max($maxIndex, $this->columnIndex($column));
        }

        $row = array_fill(0, $maxIndex + 1, '');
        foreach ($cells as $column => $value) {
            $row[$this->columnIndex($column)] = trim($value);
        }

        return $row;
    }

    private function columnIndex(string $column): int
    {
        $index = 0;
        $length = strlen($column);
        for ($i = 0; $i < $length; $i++) {
            $index = ($index * 26) + (ord($column[$i]) - 64);
        }

        return max(0, $index - 1);
    }
}
