<?php

declare(strict_types=1);

namespace App\Data\Reports;

use App\Support\Reports\ListExportLimit;
use Illuminate\Support\Collection;

/**
 * @template TModel of object
 */
final readonly class ListExportResult
{
    /**
     * @param  Collection<int, TModel>  $rows
     */
    public function __construct(
        public Collection $rows,
        public bool $truncated,
        public int $limit = ListExportLimit::MAX,
    ) {}
}
