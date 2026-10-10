<?php

declare(strict_types=1);

namespace App\Actions\Checkmate;

use App\Data\Checkmate\CheckmateDashboardData;
use App\Enums\WorkShiftStatus;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Models\WorkVisit;

/**
 * Checkmate-dashboard (docs/CHECKMATE.md): wie is waar — snelacties,
 * aanwezigheid, bezoeken vandaag, seats en klanten. Facility-KPI's
 * (meldingen, taken, units) vallen buiten de Checkmate-whitelist.
 */
class BuildCheckmateDashboardDataAction
{
    public function handle(Tenant $tenant): CheckmateDashboardData
    {
        $tenantId = (int) $tenant->id;

        $presentNow = WorkShift::query()
            ->where('tenant_id', $tenantId)
            ->where('status', WorkShiftStatus::Open)
            ->whereDoesntHave('breaks', fn ($query) => $query->whereNull('ended_at'))
            ->count();

        $visitsToday = WorkVisit::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('started_at', today())
            ->count();

        $activeCustomers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $activeWorkers = Worker::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        // Seats = collega's (admin/medewerker) + uitvoerders — billing-basis.
        // Los van het aantal uitvoerders zelf (kpi 'workers').
        $seatsQty = $tenant->billing_seats_qty ?? $tenant->maxSeatsLimit();
        $seatsValue = $seatsQty !== null
            ? $tenant->currentSeatsCount().' / '.(int) $seatsQty
            : (string) $tenant->currentSeatsCount();

        // Pulse tot de eerste dienst: nieuwe tenants zien meteen dat ze de
        // Clock Point-QR moeten mailen/afdrukken vóór uitvoerders kunnen inklokken.
        $pulseClockPointQr = ! WorkShift::query()->where('tenant_id', $tenantId)->exists();

        return new CheckmateDashboardData(
            quickTiles: [
                [
                    'key' => 'add_customer',
                    'icon' => 'building-office',
                    'tone' => 'locations',
                    'title' => 'dashboard.checkmate.actions.add_customer_title',
                    'body' => 'dashboard.checkmate.actions.add_customer_body',
                    'href' => route('customers.index', ['create' => 1]),
                ],
                [
                    'key' => 'add_worker',
                    'icon' => 'team',
                    'tone' => 'open_tasks',
                    'title' => 'dashboard.checkmate.actions.add_worker_title',
                    'body' => 'dashboard.checkmate.actions.add_worker_body',
                    'href' => route('team.index', ['section' => 'teams', 'create_worker' => 1]),
                ],
                [
                    'key' => 'presence',
                    'icon' => 'clock',
                    'tone' => 'units',
                    'title' => 'dashboard.checkmate.actions.presence_title',
                    'body' => 'dashboard.checkmate.actions.presence_body',
                    'href' => route('time.presence.index'),
                ],
                [
                    'key' => 'clock_point',
                    'icon' => 'qr',
                    'tone' => 'new_issues',
                    'title' => 'dashboard.checkmate.actions.clock_point_title',
                    'body' => 'dashboard.checkmate.actions.clock_point_body',
                    'href' => route('time.clock-points.index'),
                ],
            ],
            kpis: [
                [
                    'key' => 'present_now',
                    'icon' => 'clock',
                    'tone' => 'present_now',
                    'label' => 'dashboard.kpi.present_now',
                    'value' => (string) $presentNow,
                    'href' => route('time.presence.index'),
                ],
                [
                    'key' => 'visits_today',
                    'icon' => 'map-pin',
                    'tone' => 'new_issues',
                    'label' => 'dashboard.checkmate.kpi.visits_today',
                    'value' => (string) $visitsToday,
                    'href' => route('time.shifts.index'),
                ],
                [
                    'key' => 'workers',
                    'icon' => 'team',
                    'tone' => 'open_tasks',
                    'label' => 'dashboard.checkmate.kpi.workers',
                    'value' => (string) $activeWorkers,
                    'href' => route('team.index', ['section' => 'teams']),
                ],
                [
                    'key' => 'seats',
                    'icon' => 'subscription',
                    'tone' => 'pending_review',
                    'label' => 'dashboard.checkmate.kpi.seats',
                    'value' => $seatsValue,
                    'href' => route('subscription.index'),
                ],
                [
                    'key' => 'customers',
                    'icon' => 'building-office',
                    'tone' => 'units',
                    'label' => 'dashboard.checkmate.kpi.customers',
                    'value' => (string) $activeCustomers,
                    'href' => route('customers.index'),
                ],
                [
                    'key' => 'clock_point_qr',
                    'icon' => 'qr',
                    'tone' => 'new_issues',
                    'label' => 'dashboard.checkmate.kpi.clock_point_qr',
                    'value' => __('dashboard.checkmate.kpi.clock_point_qr_value'),
                    'action' => 'open_clock_point_qr',
                    'pulse' => $pulseClockPointQr,
                ],
            ],
            recentVisits: WorkVisit::query()
                ->where('tenant_id', $tenantId)
                ->with(['worker', 'location.customer'])
                ->latest('started_at')
                ->limit(8)
                ->get(),
            needsWorkers: Worker::query()->where('tenant_id', $tenantId)->count() === 0,
            needsCustomers: Customer::query()->where('tenant_id', $tenantId)->count() === 0,
            presencePending: $tenant->presenceComplianceRequested(),
            // CIAO is bij checkmate standaard aan: zonder BCE/btw worden
            // submissions lokaal skipped — nudge richting Instellingen.
            presenceMissingEmployer: $tenant->presenceComplianceEnabled()
                && ! filled($tenant->enterprise_number)
                && ! filled($tenant->foreign_vat_number),
        );
    }
}
