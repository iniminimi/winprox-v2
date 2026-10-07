<?php

namespace App\Actions\Time;

use App\Data\Time\CopyRosterWeeksData;
use App\Enums\AbsenceRequestStatus;
use App\Enums\PlannedShiftStatus;
use App\Enums\ShiftTypeKind;
use App\Events\Time\ScheduleCopied;
use App\Exceptions\RosterValidationException;
use App\Models\AbsenceRequest;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CopyRosterWeeksAction
{
    public const STATUS_OK = 'ok';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUS_SAME_WEEK = 'same_week';

    public const STATUS_FAILED = 'failed';

    public const MAX_WEEKS = 26;

    public function __construct(private ResolveRosterWeekAction $resolveWeek) {}

    /**
     * Kopieer de werkshifts van de bronweek naar N opeenvolgende doelweken.
     *
     * Per doelweek een eigen transactie (alles-of-niets binnen een week);
     * een niet-lege doelweek wordt overgeslagen en gerapporteerd zonder de
     * andere weken te blokkeren. Afwezigheidsblokken en notities worden
     * niet meegekopieerd. Een werkshift die op een dag valt waarop de
     * worker goedgekeurd verlof heeft (of structureel onbeschikbaar is —
     * punt 4 hakt hier in via absentDates()) wordt overgeslagen en
     * gerapporteerd.
     *
     * @return array{
     *     weeks: list<array{
     *         week: string,
     *         status: string,
     *         created: int,
     *         shifts: list<PlannedShift>,
     *         skipped: list<array{worker: string, date: string, label: string, reason: string}>,
     *         message: ?string,
     *     }>,
     *     created_total: int,
     * }
     */
    public function handle(Tenant $tenant, CopyRosterWeeksData $data, ?int $actorUserId): array
    {
        TimeModuleAccess::assertEnabledForTenantId((int) $tenant->id);

        [$sourceMonday, , $sourceDates] = $this->resolveWeek->handle($data->sourceWeekStart);
        [$firstTargetMonday] = $this->resolveWeek->handle($data->targetWeekStart);
        $weekCount = max(1, min(self::MAX_WEEKS, $data->weekCount));

        $workerIds = array_values(array_unique(array_map('intval', $data->workerIds)));
        $this->assertWorkersBelongToTenant((int) $tenant->id, $workerIds);

        $source = PlannedShift::query()
            ->with('shiftType')
            ->whereIn('worker_id', $workerIds ?: [0])
            ->where('kind', ShiftTypeKind::Work->value)
            ->whereBetween('work_date', [$sourceDates[0], $sourceDates[6]])
            ->orderBy('start_time')
            ->get();

        $workerNames = Worker::query()
            ->whereIn('id', $workerIds ?: [0])
            ->pluck('last_name', 'id')
            ->map(fn ($name) => (string) $name)
            ->all();

        $lastTargetSunday = $firstTargetMonday->copy()->addWeeks($weekCount)->subDay();
        $absent = $this->absentDates(
            (int) $tenant->id,
            $workerIds,
            $firstTargetMonday->toDateString(),
            $lastTargetSunday->toDateString(),
        );

        $weeks = [];
        $createdTotal = 0;
        for ($i = 0; $i < $weekCount; $i++) {
            $targetMonday = $firstTargetMonday->copy()->addWeeks($i);
            $week = $this->copyOneWeek(
                $tenant,
                $source,
                $sourceMonday,
                $targetMonday,
                $workerIds,
                $workerNames,
                $absent,
                $actorUserId,
            );
            $createdTotal += $week['created'];
            $weeks[] = $week;
        }

        return ['weeks' => $weeks, 'created_total' => $createdTotal];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PlannedShift>  $source
     * @param  list<int>  $workerIds
     * @param  array<int, string>  $workerNames
     * @param  array<int, array<string, true>>  $absent
     * @return array{week: string, status: string, created: int, shifts: list<PlannedShift>, skipped: list<array{worker: string, date: string, label: string, reason: string}>, message: ?string}
     */
    private function copyOneWeek(
        Tenant $tenant,
        $source,
        Carbon $sourceMonday,
        Carbon $targetMonday,
        array $workerIds,
        array $workerNames,
        array $absent,
        ?int $actorUserId,
    ): array {
        $week = $targetMonday->toDateString();
        $empty = ['week' => $week, 'status' => self::STATUS_FAILED, 'created' => 0, 'shifts' => [], 'skipped' => [], 'message' => null];

        if ($week === $sourceMonday->toDateString()) {
            return array_merge($empty, ['status' => self::STATUS_SAME_WEEK]);
        }

        $targetDates = [];
        for ($i = 0; $i < 7; $i++) {
            $targetDates[] = $targetMonday->copy()->addDays($i)->toDateString();
        }

        try {
            return DB::transaction(function () use (
                $tenant, $source, $sourceMonday, $targetMonday, $targetDates,
                $workerIds, $workerNames, $absent, $actorUserId, $week, $empty,
            ) {
                $occupied = PlannedShift::query()
                    ->whereIn('worker_id', $workerIds ?: [0])
                    ->whereBetween('work_date', [$targetDates[0], $targetDates[6]])
                    ->lockForUpdate()
                    ->exists();

                if ($occupied) {
                    return array_merge($empty, ['status' => self::STATUS_OCCUPIED]);
                }

                $offsetDays = (int) round(($targetMonday->getTimestamp() - $sourceMonday->getTimestamp()) / 86400);
                $created = [];
                $skipped = [];
                foreach ($source as $shift) {
                    $targetDate = $shift->work_date->copy()->addDays($offsetDays)->toDateString();

                    if (isset($absent[(int) $shift->worker_id][$targetDate])) {
                        $skipped[] = [
                            'worker' => $workerNames[(int) $shift->worker_id] ?? (string) $shift->worker_id,
                            'date' => $targetDate,
                            'label' => $this->shiftLabel($shift),
                            'reason' => 'absence',
                        ];
                        continue;
                    }

                    $created[] = PlannedShift::create([
                        'tenant_id' => $tenant->id,
                        'worker_id' => $shift->worker_id,
                        'work_date' => $targetDate,
                        'shift_type_id' => $shift->shift_type_id,
                        'kind' => $shift->kind,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                        'break_minutes' => $shift->break_minutes,
                        'unit_id' => $shift->unit_id,
                        'unit_code' => $shift->unit_code,
                        'unit_name' => $shift->unit_name,
                        'location_id' => $shift->location_id,
                        'status' => PlannedShiftStatus::Draft,
                    ]);
                }

                event(new ScheduleCopied(
                    tenantId: (int) $tenant->id,
                    actorUserId: $actorUserId,
                    sourceWeekStart: $sourceMonday->toDateString(),
                    targetWeekStart: $week,
                    workerIds: $workerIds,
                    count: count($created),
                ));

                return array_merge($empty, [
                    'status' => self::STATUS_OK,
                    'created' => count($created),
                    'shifts' => $created,
                    'skipped' => $skipped,
                ]);
            });
        } catch (RosterValidationException $e) {
            return array_merge($empty, ['message' => $e->getMessage()]);
        }
    }

    /**
     * Datums waarop een worker afwezig is via een goedgekeurde aanvraag.
     * Punt 4 (structurele onbeschikbaarheid) breidt deze kaart uit met
     * weekdag-matches uit worker_unavailabilities.
     *
     * @param  list<int>  $workerIds
     * @return array<int, array<string, true>>
     */
    private function absentDates(int $tenantId, array $workerIds, string $from, string $to): array
    {
        if ($workerIds === []) {
            return [];
        }

        $requests = AbsenceRequest::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('worker_id', $workerIds)
            ->where('status', AbsenceRequestStatus::Approved->value)
            ->where('date_from', '<=', $to)
            ->where('date_to', '>=', $from)
            ->get(['worker_id', 'date_from', 'date_to']);

        $map = [];
        foreach ($requests as $request) {
            $day = Carbon::parse($request->date_from->toDateString());
            $end = Carbon::parse($request->date_to->toDateString());
            while ($day->lte($end)) {
                $map[(int) $request->worker_id][$day->toDateString()] = true;
                $day->addDay();
            }
        }

        return $map;
    }

    private function shiftLabel(PlannedShift $shift): string
    {
        if ($shift->shiftType?->code) {
            return (string) $shift->shiftType->code;
        }

        return sprintf('%s-%s', (string) $shift->start_time, (string) $shift->end_time);
    }

    /**
     * @param  list<int>  $workerIds
     */
    private function assertWorkersBelongToTenant(int $tenantId, array $workerIds): void
    {
        if ($workerIds === []) {
            return;
        }

        $count = Worker::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $workerIds)
            ->count();

        if ($count !== count($workerIds)) {
            throw new RosterValidationException('time.schedule.errors.unknown_worker');
        }
    }
}
