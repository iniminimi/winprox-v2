<?php

namespace App\Actions\Retention;

use App\Enums\WorkShiftStatus;
use App\Models\AbsenceRequest;
use App\Models\AbsenceRequestPhoto;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\TenantTimeArchive;
use App\Models\User;
use App\Models\WorkBreak;
use App\Models\WorkShift;
use App\Models\WorkShiftTaskLog;
use App\Models\WorkVisit;
use App\Support\Time\TimeArchiveCsv;
use App\Support\Time\TimeArchiveWindow;
use App\Support\Time\TimeArchiveZip;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Schrijft één jaar van één tenant in een zip en wist daarna alleen rijen
 * waarvan het id in dat zipbestand staat én die nog in dat jaar vallen.
 * Een jaar dat de tenant nog moet kunnen zien wordt niet aangeraakt.
 * Fotobestanden horen hier niet in.
 */
final class ArchiveTenantTimeYearAction
{
    /**
     * @return array{skipped: bool, reason: ?string, deleted: array{planned: int, shifts: int, requests: int}}
     */
    public function handle(Tenant $tenant, int $year, bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $empty = ['skipped' => false, 'reason' => null, 'deleted' => ['planned' => 0, 'shifts' => 0, 'requests' => 0]];

        if (! $tenant->hasTimeModule() && ! $tenant->checkmateMode()) {
            return ['skipped' => true, 'reason' => 'no_time', 'deleted' => $empty['deleted']];
        }

        $now = TimeArchiveWindow::brussels($now);
        if ($year > TimeArchiveWindow::latestArchivableYear($now) || $year < 2000) {
            return ['skipped' => true, 'reason' => 'year_still_visible', 'deleted' => $empty['deleted']];
        }

        $locale = $this->locale($tenant);
        $planned = $this->plannedRows($tenant, $year);
        $shifts = $this->shiftRows($tenant, $year);
        $shiftIds = array_map(static fn (array $row): int => (int) $row[0], $shifts);
        $breaks = $this->breakRows($tenant, $shiftIds);
        $visits = $this->visitRows($tenant, $shiftIds);
        $tasks = $this->taskRows($tenant, $shiftIds, $locale);
        $requests = $this->requestRows($tenant, $year);

        if ($planned === [] && $shifts === [] && $requests === [] && ! $this->zipExists($tenant, $year)) {
            return $empty;
        }

        if ($dryRun) {
            return [
                'skipped' => false,
                'reason' => null,
                'deleted' => [
                    'planned' => count($planned),
                    'shifts' => count($shifts),
                    'requests' => count(array_filter(
                        $requests,
                        static fn (array $row): bool => (int) ($row[12] ?? 0) === 0,
                    )),
                ],
            ];
        }

        $path = $tenant->id > 0
            ? TenantTimeArchive::DIRECTORY.'/'.$tenant->id.'/jaar-'.$year.'.zip'
            : throw new RuntimeException('archive_unreadable');

        $merged = [
            'rooster.csv' => $this->merge($path, 'rooster.csv', $planned),
            'uren.csv' => $this->merge($path, 'uren.csv', $shifts),
            'pauzes.csv' => $this->merge($path, 'pauzes.csv', $breaks),
            'bezoeken.csv' => $this->merge($path, 'bezoeken.csv', $visits),
            'taken.csv' => $this->merge($path, 'taken.csv', $tasks),
            'aanvragen.csv' => $this->merge($path, 'aanvragen.csv', $requests),
        ];

        $entries = [
            'rooster.csv' => TimeArchiveCsv::encode($this->header($locale, 'roster', [
                'worker', 'date', 'kind', 'code', 'label', 'start', 'end', 'break', 'portion', 'unit_code', 'unit', 'location', 'note', 'status',
            ]), $merged['rooster.csv']),
            'uren.csv' => TimeArchiveCsv::encode($this->header($locale, 'shifts', [
                'worker', 'team', 'clock_in', 'clock_out', 'break', 'clock_in_point', 'clock_out_point', 'source_in', 'source_out', 'latitude', 'longitude', 'hops', 'status',
            ]), $merged['uren.csv']),
            'pauzes.csv' => TimeArchiveCsv::encode($this->header($locale, 'breaks', [
                'shift_id', 'started_at', 'ended_at', 'type',
            ]), $merged['pauzes.csv']),
            'bezoeken.csv' => TimeArchiveCsv::encode($this->header($locale, 'visits', [
                'shift_id', 'unit', 'location', 'started_at', 'ended_at', 'start_lat', 'start_lng', 'end_lat', 'end_lng',
            ]), $merged['bezoeken.csv']),
            'taken.csv' => TimeArchiveCsv::encode($this->header($locale, 'tasks', [
                'shift_id', 'task', 'started_at', 'ended_at',
            ]), $merged['taken.csv']),
            'aanvragen.csv' => TimeArchiveCsv::encode($this->header($locale, 'requests', [
                'worker', 'kind', 'code', 'label', 'date_from', 'date_to', 'portion', 'note', 'status', 'decision', 'decided_at', 'photos',
            ]), $merged['aanvragen.csv']),
        ];

        TimeArchiveZip::add($path, $entries);
        $this->remember($tenant, $year, $path, $now);

        $deleted = $this->deleteCovered($tenant, $year, $path);

        return ['skipped' => false, 'reason' => null, 'deleted' => $deleted];
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private function header(string $locale, string $group, array $keys): array
    {
        $header = ['id'];
        foreach ($keys as $key) {
            $header[] = (string) __('time.archive.columns.'.$group.'.'.$key, [], $locale);
        }

        return $header;
    }

    /**
     * @param  list<list<string>>  $fresh
     * @return list<list<string>>
     */
    private function merge(string $path, string $name, array $fresh): array
    {
        $byId = [];
        if (Storage::disk(TenantTimeArchive::DISK)->exists($path)) {
            $contents = TimeArchiveZip::read($path, $name);
            foreach (TimeArchiveCsv::decode($contents ?? '') as $row) {
                $byId[(int) $row[0]] = $row;
            }
        }
        foreach ($fresh as $row) {
            $byId[(int) $row[0]] = $row;
        }
        ksort($byId);

        return array_values($byId);
    }

    /**
     * @return array{planned: int, shifts: int, requests: int}
     */
    private function deleteCovered(Tenant $tenant, int $year, string $path): array
    {
        $plannedIds = $this->plannedIds($tenant, $year);
        $shiftIds = $this->shiftIds($tenant, $year);
        $requestIds = $this->requestIdsWithoutPhotos($tenant, $year);

        $this->assertCovered($path, 'rooster.csv', $plannedIds);
        $this->assertCovered($path, 'uren.csv', $shiftIds);
        $this->assertCovered($path, 'aanvragen.csv', $requestIds);
        $this->assertCovered($path, 'pauzes.csv', $this->childIds(WorkBreak::class, $tenant, $shiftIds));
        $this->assertCovered($path, 'bezoeken.csv', $this->childIds(WorkVisit::class, $tenant, $shiftIds));
        $this->assertCovered($path, 'taken.csv', $this->childIds(WorkShiftTaskLog::class, $tenant, $shiftIds));

        return [
            'planned' => $this->deleteIds(PlannedShift::class, (int) $tenant->id, $plannedIds, function (Builder $query) use ($year): void {
                $this->constrainDate($query, 'work_date', $year);
            }),
            'shifts' => $this->deleteIds(WorkShift::class, (int) $tenant->id, $shiftIds, function (Builder $query) use ($year): void {
                $this->constrainClosedShift($query, $year);
            }),
            'requests' => $this->deleteIds(AbsenceRequest::class, (int) $tenant->id, $requestIds, function (Builder $query) use ($tenant, $year): void {
                $this->constrainDate($query, 'date_to', $year);
                $query->whereDoesntHave('photos', function (Builder $photos) use ($tenant): void {
                    $photos->withoutGlobalScopes()->where('tenant_id', $tenant->id);
                });
            }),
        ];
    }

    /**
     * @param  list<int>  $ids
     */
    private function assertCovered(string $path, string $name, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $contents = TimeArchiveZip::read($path, $name);
        if ($contents === null) {
            throw new RuntimeException('archive_incomplete');
        }

        $archived = [];
        foreach (TimeArchiveCsv::decode($contents) as $row) {
            $archived[(int) $row[0]] = true;
        }

        foreach ($ids as $id) {
            if (! isset($archived[$id])) {
                throw new RuntimeException('archive_incomplete');
            }
        }
    }

    /**
     * @param  class-string  $model
     * @param  list<int>  $ids
     */
    private function deleteIds(string $model, int $tenantId, array $ids, callable $constrain): int
    {
        $deleted = 0;
        foreach (array_chunk($ids, 200) as $chunk) {
            $query = $model::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->whereIn('id', $chunk);
            $constrain($query);
            $deleted += $query->delete();
        }

        return $deleted;
    }

    private function constrainDate(Builder $query, string $column, int $year): void
    {
        $query->where($column, '>=', sprintf('%04d-01-01', $year))
            ->where($column, '<=', sprintf('%04d-12-31', $year));
    }

    private function constrainClosedShift(Builder $query, int $year): void
    {
        $query->where('clock_in_at', '>=', TimeArchiveWindow::yearStart($year))
            ->where('clock_in_at', '<', TimeArchiveWindow::yearEnd($year))
            ->whereNotNull('clock_out_at')
            ->where('status', '!=', WorkShiftStatus::Open->value);
    }

    /**
     * @return list<int>
     */
    private function plannedIds(Tenant $tenant, int $year): array
    {
        return PlannedShift::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('work_date', '>=', sprintf('%04d-01-01', $year))
            ->where('work_date', '<=', sprintf('%04d-12-31', $year))
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function shiftIds(Tenant $tenant, int $year): array
    {
        $query = WorkShift::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id);
        $this->constrainClosedShift($query, $year);

        return $query->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    /**
     * @return list<int>
     */
    private function requestIdsWithoutPhotos(Tenant $tenant, int $year): array
    {
        $withPhotos = AbsenceRequestPhoto::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->pluck('absence_request_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $query = AbsenceRequest::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id);
        $this->constrainDate($query, 'date_to', $year);
        if ($withPhotos !== []) {
            $query->whereNotIn('id', $withPhotos);
        }

        return $query->orderBy('id')->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  class-string  $model
     * @param  list<int>  $shiftIds
     * @return list<int>
     */
    private function childIds(string $model, Tenant $tenant, array $shiftIds): array
    {
        if ($shiftIds === []) {
            return [];
        }

        return $model::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('work_shift_id', $shiftIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<list<string>>
     */
    private function plannedRows(Tenant $tenant, int $year): array
    {
        $rows = [];
        PlannedShift::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('work_date', '>=', sprintf('%04d-01-01', $year))
            ->where('work_date', '<=', sprintf('%04d-12-31', $year))
            ->with(['worker', 'shiftType', 'location'])
            ->orderBy('id')
            ->chunkById(200, function ($shifts) use (&$rows): void {
                foreach ($shifts as $shift) {
                    $rows[] = [
                        (string) $shift->id,
                        $this->cell($shift->worker?->displayName()),
                        $shift->work_date?->toDateString() ?? '',
                        $this->cell($shift->kind?->value),
                        $this->cell($shift->shiftType?->code),
                        $this->cell($shift->shiftType?->label),
                        $this->clock($shift->start_time),
                        $this->clock($shift->end_time),
                        (string) ($shift->break_minutes ?? 0),
                        $shift->day_portion === null ? '' : (string) $shift->day_portion,
                        $this->cell($shift->unit_code),
                        $this->cell($shift->unit_name),
                        $this->cell($shift->location?->name),
                        $this->cell($shift->description),
                        $this->cell($shift->status?->value),
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function shiftRows(Tenant $tenant, int $year): array
    {
        $rows = [];
        $query = WorkShift::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['worker', 'team', 'clockInClockPoint', 'clockOutClockPoint']);
        $this->constrainClosedShift($query, $year);
        $query->orderBy('id')->chunkById(200, function ($shifts) use (&$rows): void {
            foreach ($shifts as $shift) {
                $rows[] = [
                    (string) $shift->id,
                    $this->cell($shift->worker?->displayName()),
                    $this->cell($shift->team?->name),
                    $this->local($shift->clock_in_at),
                    $this->local($shift->clock_out_at),
                    (string) ($shift->total_break_minutes ?? 0),
                    $this->cell($shift->clockInClockPoint?->name),
                    $this->cell($shift->clockOutClockPoint?->name),
                    $this->cell($shift->clock_in_source?->value),
                    $this->cell($shift->clock_out_source?->value),
                    $this->coordinate($shift->clock_in_latitude),
                    $this->coordinate($shift->clock_in_longitude),
                    $this->cell($this->hopsText($shift->location_hops)),
                    $this->cell($shift->status?->value),
                ];
            }
        });

        return $rows;
    }

    /**
     * @param  list<int>  $shiftIds
     * @return list<list<string>>
     */
    private function breakRows(Tenant $tenant, array $shiftIds): array
    {
        if ($shiftIds === []) {
            return [];
        }

        $rows = [];
        WorkBreak::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('work_shift_id', $shiftIds)
            ->orderBy('id')
            ->chunkById(200, function ($breaks) use (&$rows): void {
                foreach ($breaks as $break) {
                    $rows[] = [
                        (string) $break->id,
                        (string) $break->work_shift_id,
                        $this->local($break->started_at),
                        $this->local($break->ended_at),
                        $this->cell($break->break_type?->value),
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<int>  $shiftIds
     * @return list<list<string>>
     */
    private function visitRows(Tenant $tenant, array $shiftIds): array
    {
        if ($shiftIds === []) {
            return [];
        }

        $rows = [];
        WorkVisit::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('work_shift_id', $shiftIds)
            ->with(['unit', 'location'])
            ->orderBy('id')
            ->chunkById(200, function ($visits) use (&$rows): void {
                foreach ($visits as $visit) {
                    $rows[] = [
                        (string) $visit->id,
                        (string) $visit->work_shift_id,
                        $this->cell($visit->unit?->name),
                        $this->cell($visit->location?->name),
                        $this->local($visit->started_at),
                        $this->local($visit->ended_at),
                        $this->coordinate($visit->start_latitude),
                        $this->coordinate($visit->start_longitude),
                        $this->coordinate($visit->end_latitude),
                        $this->coordinate($visit->end_longitude),
                    ];
                }
            });

        return $rows;
    }

    /**
     * @param  list<int>  $shiftIds
     * @return list<list<string>>
     */
    private function taskRows(Tenant $tenant, array $shiftIds, string $locale): array
    {
        if ($shiftIds === []) {
            return [];
        }

        $rows = [];
        WorkShiftTaskLog::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('work_shift_id', $shiftIds)
            ->with('task')
            ->orderBy('id')
            ->chunkById(200, function ($logs) use (&$rows, $locale): void {
                foreach ($logs as $log) {
                    $rows[] = [
                        (string) $log->id,
                        (string) $log->work_shift_id,
                        $this->cell($log->task?->displayDescription($locale)),
                        $this->local($log->started_at),
                        $this->local($log->ended_at),
                    ];
                }
            });

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function requestRows(Tenant $tenant, int $year): array
    {
        $photoCounts = AbsenceRequestPhoto::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->selectRaw('absence_request_id, count(*) as aggregate')
            ->groupBy('absence_request_id')
            ->pluck('aggregate', 'absence_request_id');

        $rows = [];
        $query = AbsenceRequest::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->with(['worker', 'shiftType']);
        $this->constrainDate($query, 'date_to', $year);
        $query->orderBy('id')->chunkById(200, function ($requests) use (&$rows, $photoCounts): void {
            foreach ($requests as $request) {
                $rows[] = [
                    (string) $request->id,
                    $this->cell($request->worker?->displayName()),
                    $this->cell($request->kind?->value),
                    $this->cell($request->shiftType?->code),
                    $this->cell($request->shiftType?->label),
                    $request->date_from?->toDateString() ?? '',
                    $request->date_to?->toDateString() ?? '',
                    $request->day_portion === null ? '' : (string) $request->day_portion,
                    $this->cell($request->description),
                    $this->cell($request->status?->value),
                    $this->cell($request->decision_description),
                    $this->local($request->decided_at),
                    (string) ((int) ($photoCounts[$request->id] ?? 0)),
                ];
            }
        });

        return $rows;
    }

    private function remember(Tenant $tenant, int $year, string $path, CarbonInterface $now): void
    {
        $row = TenantTimeArchive::query()->withoutGlobalScopes()->firstOrNew([
            'tenant_id' => $tenant->id,
            'kind' => TenantTimeArchive::KIND_YEAR,
            'period_key' => (string) $year,
        ]);
        $row->path = $path;
        $row->bytes = (int) Storage::disk(TenantTimeArchive::DISK)->size($path);
        if (! $row->exists) {
            $row->available_until = TimeArchiveWindow::yearAvailableUntil($year, $now);
        }
        $row->save();
    }

    private function zipExists(Tenant $tenant, int $year): bool
    {
        $path = TenantTimeArchive::DIRECTORY.'/'.$tenant->id.'/jaar-'.$year.'.zip';

        return Storage::disk(TenantTimeArchive::DISK)->exists($path);
    }

    private function locale(Tenant $tenant): string
    {
        $locale = User::query()
            ->where('tenant_id', $tenant->id)
            ->where('role', User::ROLE_ADMIN)
            ->where('is_active', true)
            ->orderBy('id')
            ->value('locale');

        return is_string($locale) && $locale !== '' ? $locale : (string) config('app.locale', 'nl');
    }

    private function local(mixed $value): string
    {
        if (! $value instanceof CarbonInterface) {
            return '';
        }

        return $value->copy()->timezone(TimeArchiveWindow::TZ)->format('Y-m-d H:i');
    }

    private function clock(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;
        if (preg_match('/^\d{2}:\d{2}/', $text) === 1) {
            return substr($text, 0, 5);
        }

        return $text;
    }

    private function coordinate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, 6, '.', '');
    }

    /**
     * @param  array<int, mixed>|null  $hops
     */
    private function hopsText(?array $hops): string
    {
        if ($hops === null || $hops === []) {
            return '';
        }

        $parts = [];
        foreach ($hops as $hop) {
            if (! is_array($hop)) {
                continue;
            }
            $from = (string) ($hop['from_clock_point_name'] ?? '');
            $to = (string) ($hop['to_clock_point_name'] ?? '');
            if ($from !== '' || $to !== '') {
                $parts[] = trim($from.' → '.$to);
            }
        }

        return implode(' | ', $parts);
    }

    private function cell(mixed $value): string
    {
        $text = $value === null ? '' : (string) $value;
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
            return "'".$text;
        }

        return $text;
    }
}
