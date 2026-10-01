<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\SummarizeCustomerWorkStatsAction;
use App\Models\Customer;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerStatsPrintController
{
    public function __invoke(Request $request, Customer $customer, SummarizeCustomerWorkStatsAction $summarize): View
    {
        Gate::authorize('view', $customer);
        abort_unless($customer->tenant?->hasTimeModule(), 404);

        $tz = (string) config('app.timezone');
        $month = is_string($request->query('month')) && preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month'))
            ? (string) $request->query('month')
            : CarbonImmutable::now($tz)->format('Y-m');

        $from = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', $tz)->startOfMonth();
        $to = $from->addMonth();

        $stats = $summarize->handle((int) $customer->tenant_id, $from, $to)
            ->get((int) $customer->id);

        $locations = Location::query()
            ->whereIn('id', array_keys($stats?->locations ?? []))
            ->get()
            ->keyBy('id');

        return view('customers.stats-print', [
            'tenant' => $customer->tenant,
            'customer' => $customer,
            'stats' => $stats,
            'locations' => $locations,
            'from' => $from,
        ]);
    }
}
