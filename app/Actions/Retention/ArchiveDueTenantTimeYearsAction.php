<?php

namespace App\Actions\Retention;

use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\WorkShift;
use App\Support\Time\TimeArchiveWindow;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Voor elke tenant met Time: jaren die niet meer zichtbaar horen te zijn,
 * één voor één. Een fout bij één tenant stopt de andere niet en wist niets extra.
 */
final class ArchiveDueTenantTimeYearsAction
{
    public function __construct(private ArchiveTenantTimeYearAction $archiveYear) {}

    /**
     * @return array{tenants: int, years: int, deleted: int, skipped: int, errors: int}
     */
    public function handle(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $now = TimeArchiveWindow::brussels($now);
        $latest = TimeArchiveWindow::latestArchivableYear($now);
        $stats = ['tenants' => 0, 'years' => 0, 'deleted' => 0, 'skipped' => 0, 'errors' => 0];

        Tenant::query()
            ->where(function ($query): void {
                $query->where('has_time_module', true)->orWhere('checkmate_mode', true);
            })
            ->orderBy('id')
            ->chunkById(50, function ($tenants) use ($latest, $dryRun, $now, &$stats): void {
                foreach ($tenants as $tenant) {
                    $stats['tenants']++;
                    try {
                        foreach ($this->dueYears($tenant, $latest) as $year) {
                            $result = $this->archiveYear->handle($tenant, $year, $dryRun, $now);
                            if ($result['skipped']) {
                                $stats['skipped']++;

                                continue;
                            }
                            $stats['years']++;
                            $stats['deleted'] += array_sum($result['deleted']);
                        }
                    } catch (Throwable $exception) {
                        $stats['errors']++;
                        report($exception);
                    }
                }
            });

        return $stats;
    }

    /**
     * @return list<int>
     */
    private function dueYears(Tenant $tenant, int $latest): array
    {
        $years = [];
        $cap = sprintf('%04d-12-31', $latest);

        PlannedShift::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('work_date', '<=', $cap)
            ->orderBy('id')
            ->select(['id', 'work_date'])
            ->chunkById(500, function ($rows) use (&$years, $latest): void {
                foreach ($rows as $row) {
                    $year = (int) $row->work_date?->year;
                    if ($year >= 2000 && $year <= $latest) {
                        $years[$year] = true;
                    }
                }
            });

        $shiftQuery = WorkShift::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('clock_in_at', '<', TimeArchiveWindow::yearEnd($latest))
            ->whereNotNull('clock_out_at')
            ->where('status', '!=', 'open');
        $shiftQuery->orderBy('id')->select(['id', 'clock_in_at'])->chunkById(500, function ($rows) use (&$years, $latest): void {
            foreach ($rows as $row) {
                if (! $row->clock_in_at instanceof \Carbon\CarbonInterface) {
                    continue;
                }
                $year = (int) $row->clock_in_at->timezone(TimeArchiveWindow::TZ)->year;
                if ($year >= 2000 && $year <= $latest) {
                    $years[$year] = true;
                }
            }
        });

        AbsenceRequest::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('date_to', '<=', $cap)
            ->orderBy('id')
            ->select(['id', 'date_to'])
            ->chunkById(500, function ($rows) use (&$years, $latest): void {
                foreach ($rows as $row) {
                    $year = (int) $row->date_to?->year;
                    if ($year >= 2000 && $year <= $latest) {
                        $years[$year] = true;
                    }
                }
            });

        $keys = array_keys($years);
        sort($keys);

        return array_map(static fn ($year): int => (int) $year, $keys);
    }
}
