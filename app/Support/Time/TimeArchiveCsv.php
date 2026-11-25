<?php

namespace App\Support\Time;

/**
 * CSV in het zipbestand. Puntkomma en UTF-8 BOM, zoals de andere exports.
 * De eerste kolom heet altijd id, zodat het wissen niet van een vertaling afhangt.
 */
final class TimeArchiveCsv
{
    /**
     * @param  list<string>  $header
     * @param  list<list<string>>  $rows
     */
    public static function encode(array $header, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $header, ';');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents === false ? '' : $contents;
    }

    /**
     * @return list<list<string>>
     */
    public static function decode(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];
        $first = true;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }
            $cells = array_map(static fn ($cell) => (string) $cell, $row);
            if ($first) {
                $first = false;
                if (($cells[0] ?? '') === 'id') {
                    continue;
                }
            }
            if (($cells[0] ?? '') === '') {
                continue;
            }
            $rows[] = $cells;
        }
        fclose($handle);

        return $rows;
    }
}
