<?php

declare(strict_types=1);

namespace App\Data\Checkmate;

use App\Models\WorkVisit;
use Illuminate\Support\Collection;

/**
 * Dashboard-data voor een Checkmate-tenant (docs/CHECKMATE.md §2):
 * snelacties, veldwerk-KPI's en de laatste klantbezoeken.
 */
final class CheckmateDashboardData
{
    /**
     * @param  list<array{key: string, icon: string, tone: string, title: string, body: string, href: string}>  $quickTiles
     * @param  list<array{key: string, icon: string, tone: string, label: string, value: string, href: string}>  $kpis
     * @param  Collection<int, WorkVisit>  $recentVisits
     */
    public function __construct(
        public array $quickTiles,
        public array $kpis,
        public Collection $recentVisits,
        public bool $needsWorkers,
        public bool $needsCustomers,
        public bool $presencePending,
    ) {}
}
