<?php

namespace App\Http\Controllers\Customers;

use App\Actions\Customers\SummarizeCustomerWorkStatsAction;
use App\Models\Customer;
use App\Models\Location;
use App\Support\Reports\CsvStreamer;
use App\Support\Time\WorkDurationFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerStatsExportController
{
    public function __invoke(Request $request, Customer $customer, SummarizeCustomerWorkStatsAction $summarize): StreamedResponse
    {
        Gate::authorize('view', $customer);
        abort_unless($customer->tenant?->hasTimeModule(), 404);

        [$from, $to, $month] = $this->period($request->query('month'));

        $stats = $summarize->handle((int) $customer->tenant_id, $from, $to)
            ->get((int) $customer->id);

        $locations = Location::query()
            ->whereIn('id', array_keys($stats?->locations ?? []))
            ->get()
            ->keyBy('id');

        $headers = [
            __('customers.stats.export_columns.location'),
            __('customers.stats.export_columns.visits'),
            __('customers.stats.export_columns.worked'),
        ];

        $rows = [
            [
                __('customers.stats.export_columns.total', ['customer' => $customer->name]),
                $stats?->visits ?? 0,
                WorkDurationFormatter::format($stats?->minutes ?? 0),
            ],
        ];

        foreach (collect($stats?->locations ?? [])->sortByDesc('minutes') as $row) {
            $location = $locations->get($row->locationId);
            $rows[] = [
                trim(($location?->name ?: '—').' '.$location?->formattedAddress()),
                $row->visits,
                WorkDurationFormatter::format($row->minutes),
            ];
        }

        return CsvStreamer::download('customer-stats-'.$month.'.csv', $headers, $rows);
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, string}
     */
    private function period(?string $month): array
    {
        $tz = (string) config('app.timezone');
        $month = is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month)
            ? $month
            : CarbonImmutable::now($tz)->format('Y-m');

        $from = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', $tz)->startOfMonth();

        return [$from, $from->addMonth(), $month];
    }
}
