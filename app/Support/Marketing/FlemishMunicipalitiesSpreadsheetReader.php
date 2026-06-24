<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Data\Marketing\MunicipalPromoLetterData;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class FlemishMunicipalitiesSpreadsheetReader
{
    /**
     * @return list<MunicipalPromoLetterData>
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

            return $this->parseRows(new SimpleXMLElement($sheetXml), $sharedStrings);
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
     * @param  list<string>  $sharedStrings
     * @return list<MunicipalPromoLetterData>
     */
    private function parseRows(SimpleXMLElement $sheet, array $sharedStrings): array
    {
        $sheet->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $headerMap = null;
        $municipalities = [];

        foreach ($sheet->xpath('//m:sheetData/m:row') ?: [] as $row) {
            $row->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = $this->readRowCells($row, $sharedStrings);

            if ($cells === []) {
                continue;
            }

            if ($headerMap === null) {
                $headerMap = $this->buildHeaderMap($cells);
                if ($headerMap === []) {
                    throw new RuntimeException('Spreadsheet header row is missing or invalid.');
                }

                continue;
            }

            $municipality = $this->mapRow($cells, $headerMap);
            if ($municipality !== null) {
                $municipalities[] = $municipality;
            }
        }

        if ($headerMap === null) {
            throw new RuntimeException('Spreadsheet is empty.');
        }

        return $municipalities;
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

    /**
     * @param  array<string, string>  $cells
     * @return array<string, string>
     */
    private function buildHeaderMap(array $cells): array
    {
        $map = [];

        foreach ($cells as $column => $label) {
            $normalized = $this->normalizeHeader($label);
            if ($normalized !== '') {
                $map[$column] = $normalized;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $cells
     * @param  array<string, string>  $headerMap
     */
    private function mapRow(array $cells, array $headerMap): ?MunicipalPromoLetterData
    {
        $values = [];
        foreach ($headerMap as $column => $header) {
            $values[$header] = trim((string) ($cells[$column] ?? ''));
        }

        $name = $values['naam'] ?? '';
        if ($name === '') {
            return null;
        }

        return new MunicipalPromoLetterData(
            name: $name,
            municipalityType: $values['gemeente_stad'] ?? '',
            streetAddress: $values['adres'] ?? '',
            postalCode: $values['postcode'] ?? '',
            municipality: $values['gemeente'] ?? $name,
            province: $values['provincie'] ?? '',
            phone: $this->nullable($values['telefoon'] ?? null),
            email: $this->resolveEmail($values),
        );
    }

    /**
     * @param  array<string, string>  $values
     */
    public function resolveEmail(array $values): ?string
    {
        foreach (['e_mail', 'e-mail', 'email', 'e_mailadres', 'mail', 'emailadres'] as $key) {
            $candidate = $this->nullable($values[$key] ?? null);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        foreach ($values as $key => $value) {
            if (! str_contains($key, 'mail')) {
                continue;
            }

            $candidate = $this->nullable($value);
            if ($candidate !== null && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function detectedHeaderKeys(string $absolutePath): array
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
            $rows = $sheet->xpath('//m:sheetData/m:row') ?: [];
            if ($rows === []) {
                return [];
            }

            $rows[0]->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = $this->readRowCells($rows[0], $sharedStrings);
            $headerMap = $this->buildHeaderMap($cells);

            return array_values(array_unique(array_filter($headerMap)));
        } finally {
            $zip->close();
        }
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = strtolower(trim($header));
        $normalized = str_replace(['/', '-'], '_', $normalized);

        return preg_replace('/\s+/', '_', $normalized) ?? '';
    }

    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
