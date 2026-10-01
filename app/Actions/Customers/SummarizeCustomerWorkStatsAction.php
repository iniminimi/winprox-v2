<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Data\Customers\CustomerWorkLocationStats;
use App\Data\Customers\CustomerWorkStats;
use App\Models\WorkVisit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Klantwerkstatistieken: gewerkte tijd + bezoeken per klant voor een
 * absolute periode [$from, $to).
 *
 * De aanroeper vertaalt de gekozen periode (bv. "oktober 2026" in de
 * tenant-tijdzone) naar concrete grenzen; deze Action vergelijkt enkel
 * absolute tijdstippen met opgeslagen WorkVisit-segmenten.
 *
 * Definities (v1 — docs/FEATURES.md §klanten):
 * - Uren = WorkVisit-aanwezigheidsduur; shift-pauzes worden NIET afgetrokken.
 * - Een open bezoek (ended_at = null) telt mee tot now().
 * - Een bezoek dat de periode overlapt telt één keer; minuten worden
 *   geclampt op de periodegrenzen (overlap + clamp).
 * - Locaties zonder customer_id vallen buiten klantstatistieken.
 * - De join loopt op tabelniveau: ook gedeactiveerde werkadressen tellen
 *   historisch mee (Customer::locations() filtert is_active — niet gebruiken).
 */
final class SummarizeCustomerWorkStatsAction
{
    /**
     * @return Collection<int, CustomerWorkStats> keyed by customer id
     */
    public function handle(int $tenantId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $now = CarbonImmutable::now();
        $appTz = (string) config('app.timezone');

        $periodFrom = $from->setTimezone($appTz);
        $periodTo = $to->setTimezone($appTz);

        $visits = WorkVisit::query()
            ->where('work_visits.tenant_id', $tenantId)
            ->where('work_visits.started_at', '<', $periodTo)
            ->where(fn ($q) => $q
                ->whereNull('work_visits.ended_at')
                ->orWhere('work_visits.ended_at', '>', $periodFrom))
            ->join('locations', 'locations.id', '=', 'work_visits.location_id')
            ->whereNotNull('locations.customer_id')
            ->get([
                'work_visits.id',
                'work_visits.started_at',
                'work_visits.ended_at',
                'locations.customer_id',
                'locations.id as location_id',
            ]);

        /** @var array<int, CustomerWorkStats> $stats */
        $stats = [];

        foreach ($visits as $visit) {
            $customerId = (int) $visit->customer_id;
            $locationId = (int) $visit->location_id;

            $customerStats = $stats[$customerId] ??= new CustomerWorkStats(customerId: $customerId);
            $locationStats = $customerStats->locations[$locationId]
                ??= new CustomerWorkLocationStats(locationId: $locationId);

            $visitStart = CarbonImmutable::instance($visit->started_at);
            $visitEnd = $visit->ended_at !== null
                ? CarbonImmutable::instance($visit->ended_at)
                : $now;

            $effectiveStart = $visitStart->greaterThan($from) ? $visitStart : $from;
            $effectiveEnd = $visitEnd->lessThan($to) ? $visitEnd : $to;
            $minutes = max(0, (int) $effectiveStart->diffInMinutes($effectiveEnd));

            $customerStats->visits++;
            $customerStats->minutes += $minutes;
            $locationStats->visits++;
            $locationStats->minutes += $minutes;
        }

        return collect($stats);
    }
}
