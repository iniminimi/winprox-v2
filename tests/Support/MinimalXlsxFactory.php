<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Import\MinimalXlsxWriter;

/** @deprecated Use MinimalXlsxWriter — kept as thin alias for older test references. */
final class MinimalXlsxFactory
{
    /**
     * @param  list<list<string>>  $rows
     */
    public static function write(string $absolutePath, array $rows): void
    {
        MinimalXlsxWriter::write($absolutePath, $rows);
    }
}
