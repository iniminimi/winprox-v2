<?php

declare(strict_types=1);

namespace App\Support\Reports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HTTP-layer CSV download helper (no business logic).
 */
final class CsvStreamer
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            // Excel-friendly UTF-8 BOM (download only — not source files).
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(
                    static fn ($value) => $value === null ? '' : (string) $value,
                    $row,
                ), ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
