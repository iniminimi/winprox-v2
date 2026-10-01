<?php

namespace App\Livewire\Customers;

use App\Actions\Customers\SummarizeCustomerWorkStatsAction;
use App\Data\Customers\CustomerWorkStats;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Platform\SupportTenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Klantstatistieken — gewerkte tijd en bezoeken per werkadres voor een
 * gekozen maand. Alleen voor tenants met de Time-module; de aggregatie
 * zelf loopt volledig via SummarizeCustomerWorkStatsAction.
 */
#[Layout('components.layouts.app')]
#[Title('WinProx')]
class Stats extends Component
{
    use AuthorizesRequests;

    public Customer $customer;

    #[Url(as: 'month')]
    public string $month = '';

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);

        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant && $tenant->hasTimeModule(), 404);
        abort_unless((int) $customer->tenant_id === (int) $tenant->id, 404);

        $this->customer = $customer;

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = CarbonImmutable::now($this->tenantTimezone())->format('Y-m');
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->periodStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->periodStart()->addMonth()->format('Y-m');
    }

    public function render(SummarizeCustomerWorkStatsAction $summarize)
    {
        $tenant = $this->resolveTenant();
        abort_unless($tenant instanceof Tenant && $tenant->hasTimeModule(), 404);

        $from = $this->periodStart();
        $to = $from->addMonth();

        $stats = $summarize->handle((int) $tenant->id, $from, $to)
            ->get((int) $this->customer->id)
            ?? new CustomerWorkStats(customerId: (int) $this->customer->id);

        $locations = Location::query()
            ->whereIn('id', array_keys($stats->locations))
            ->get()
            ->keyBy('id');

        $locationRows = collect($stats->locations)
            ->sortByDesc('minutes')
            ->map(fn ($row) => [
                'stats' => $row,
                'location' => $locations->get($row->locationId),
            ])
            ->values()
            ->all();

        return view('livewire.customers.stats', [
            'stats' => $stats,
            'locationRows' => $locationRows,
            'from' => $from,
            'to' => $to,
            'exportUrl' => route('customers.stats.export', [
                'customer' => $this->customer,
                'month' => $this->month,
            ]),
            'printUrl' => route('customers.stats.print', [
                'customer' => $this->customer,
                'month' => $this->month,
            ]),
        ]);
    }

    private function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d',
            $this->month.'-01',
            $this->tenantTimezone(),
        )->startOfMonth();
    }

    private function tenantTimezone(): string
    {
        return (string) config('app.timezone');
    }

    private function resolveTenant(): ?Tenant
    {
        if (auth()->user()?->is_superuser && SupportTenantContext::isActive()) {
            return Tenant::query()->find(SupportTenantContext::activeTenantId());
        }

        return auth()->user()?->tenant;
    }
}
