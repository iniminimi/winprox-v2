<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class PromoCampaignSpreadsheetReader
{
    /**
     * @return list<string>
     */
    public function detectedHeaderLabels(string $absolutePath): array
    {
        $headerMap = $this->readHeaderMap($absolutePath);

        return array_values(array_unique(array_filter($headerMap)));
    }

    /**
     * @param  array<string, string>  $columnMapping internal field => spreadsheet header label
     * @return list<array<string, string>>
     */
    public function readRows(string $absolutePath, array $columnMapping): array
    {
        if ($columnMapping === []) {
            throw new RuntimeException('Column mapping is required.');
        }

        $headerMap = $this->readHeaderMap($absolutePath);
        $columnToHeader = [];
        foreach ($columnMapping as $internalField => $headerLabel) {
            $headerLabel = trim($headerLabel);
            if ($headerLabel === '') {
                continue;
            }

            $column = array_search($headerLabel, $headerMap, true);
            if ($column === false) {
                throw new RuntimeException("Spreadsheet column not found: {$headerLabel}");
            }

            $columnToHeader[$internalField] = $column;
        }

        if (! isset($columnToHeader['name'])) {
            throw new RuntimeException('Column mapping must include name.');
        }

        $zip = $this->openSpreadsheet($absolutePath);

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new RuntimeException('Worksheet sheet1.xml not found in spreadsheet.');
            }

            $sheet = new SimpleXMLElement($sheetXml);
            $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $rows = [];
            $isHeader = true;

            foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $row) {
                $row->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                $cells = $this->readRowCells($row, $sharedStrings);

                if ($cells === []) {
                    continue;
                }

                if ($isHeader) {
                    $isHeader = false;

                    continue;
                }

                $mapped = [];
                foreach ($columnToHeader as $internalField => $column) {
                    $mapped[$internalField] = trim((string) ($cells[$column] ?? ''));
                }

                if ($mapped['name'] === '') {
                    continue;
                }

                $rows[] = $mapped;
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<string, string> column letter => header label
     */
    private function readHeaderMap(string $absolutePath): array
    {
        $zip = $this->openSpreadsheet($absolutePath);

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new RuntimeException('Worksheet sheet1.xml not found in spreadsheet.');
            }

            $sheet = new SimpleXMLElement($sheetXml);
            $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $rows = $sheet->xpath('//m:sheetData/m:row') ?: [];
            if ($rows === []) {
                return [];
            }

            $rows[0]->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = $this->readRowCells($rows[0], $sharedStrings);

            return $this->buildHeaderMap($cells);
        } finally {
            $zip->close();
        }
    }

    private function openSpreadsheet(string $absolutePath): ZipArchive
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Spreadsheet not found: {$absolutePath}");
        }

        $zip = new ZipArchive();
        if ($zip->open($absolutePath) !== true) {
            throw new RuntimeException("Unable to open spreadsheet: {$absolutePath}");
        }

        return $zip;
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
     * @param  array<string, string>  $cells
     * @return array<string, string>
     */
    private function buildHeaderMap(array $cells): array
    {
        $map = [];
        foreach ($cells as $column => $label) {
            $label = trim($label);
            if ($label !== '') {
                $map[$column] = $label;
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
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

            $valueNode = $cell->xpath('m:v') ?: [];
            if ($valueNode === []) {
                continue;
            }

            $raw = (string) $valueNode[0];
            $type = (string) $cell['t'];
            $cells[$column] = $type === 's'
                ? ($sharedStrings[(int) $raw] ?? '')
                : $raw;
        }

        return $cells;
    }
}
