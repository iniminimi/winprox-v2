<?php

declare(strict_types=1);

namespace App\Support\Import;

use RuntimeException;

/**
 * Reads unit/worker-style import files as a header row + data rows.
 */
final class TabularImportReader
{
    public function __construct(
        private XlsxMatrixReader $xlsxReader = new XlsxMatrixReader(),
    ) {}

    /**
     * @return array{headers: list<string>, rows: list<array{line: int, values: list<string>}}}
     */
    public function read(string $filePath, string $originalName): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        return match ($extension) {
            'xlsx' => $this->fromMatrix($this->xlsxReader->read($filePath)),
            'csv', 'txt' => $this->fromCsv($filePath),
            default => throw new RuntimeException('unsupported_import_format'),
        };
    }

    /**
     * @param  list<list<string>>  $matrix
     * @return array{headers: list<string>, rows: list<array{line: int, values: list<string>}}}
     */
    private function fromMatrix(array $matrix): array
    {
        if ($matrix === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(
            static fn ($h) => trim(strtolower((string) $h)),
            $matrix[0],
        );

        $rows = [];
        $line = 2;
        for ($i = 1, $count = count($matrix); $i < $count; $i++, $line++) {
            $values = $matrix[$i];
            if ($this->isEmptyRow($values)) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'values' => $values,
            ];
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: list<string>, rows: list<array{line: int, values: list<string>}}}
     */
    private function fromCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException('unreadable');
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                return ['headers' => [], 'rows' => []];
            }

            $headers = array_map(
                static fn ($h) => trim(strtolower((string) $h)),
                $headers,
            );
            if ($headers !== []) {
                $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            }

            $rows = [];
            $line = 2;
            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isEmptyRow($row)) {
                    $line++;
                    continue;
                }

                $rows[] = [
                    'line' => $line,
                    'values' => array_map(static fn ($v) => trim((string) $v), $row),
                ];
                $line++;
            }

            return ['headers' => $headers, 'rows' => $rows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  list<mixed>  $values
     */
    private function isEmptyRow(array $values): bool
    {
        return array_filter($values, static fn ($v) => trim((string) $v) !== '') === [];
    }
}
