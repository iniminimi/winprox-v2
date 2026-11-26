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
     * @param  list<array{key: string, icon: string, tone: string, label: string, value?: string|null, body?: string|null, href?: string|null, action?: string|null, pulse?: bool}>  $kpis
     * @param  Collection<int, WorkVisit>  $recentVisits
     * @param  list<array{id: int, name: string}>  $workersMissingSsin
     */
    public function __construct(
        public array $quickTiles,
        public array $kpis,
        public Collection $recentVisits,
        public array $workersMissingSsin,
        public bool $needsWorkers,
        public bool $needsCustomers,
        public bool $presencePending,
        public bool $presenceMissingEmployer,
    ) {}

    /**
     * @param  list<mixed>  $hiddenKeys
     * @return array{
     *     quickTiles: list<array<string, mixed>>,
     *     kpis: list<array<string, mixed>>,
     *     hidden: list<array{kind: string, tile: array<string, mixed>>}
     * }
     */
    public function partitionTiles(array $hiddenKeys): array
    {
        $hiddenLookup = [];
        foreach ($hiddenKeys as $key) {
            if (is_string($key)) {
                $hiddenLookup[$key] = true;
            }
        }

        $visibleQuick = [];
        $visibleKpis = [];
        $hidden = [];

        foreach ($this->quickTiles as $tile) {
            if (isset($hiddenLookup[$tile['key']])) {
                $hidden[] = ['kind' => 'quick', 'tile' => $tile];
            } else {
                $visibleQuick[] = $tile;
            }
        }

        foreach ($this->kpis as $tile) {
            if (isset($hiddenLookup[$tile['key']])) {
                $hidden[] = ['kind' => 'kpi', 'tile' => $tile];
            } else {
                $visibleKpis[] = $tile;
            }
        }

        return [
            'quickTiles' => $visibleQuick,
            'kpis' => $visibleKpis,
            'hidden' => $hidden,
        ];
    }
}
