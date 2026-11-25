<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Team\CountWorkerAdminFollowUpAction;
use App\Actions\Time\CountPendingAbsenceRequestsAction;
use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Actions\Time\ListLocationStaffingShortfallsAction;
use App\Data\Dashboard\DashboardStatsData;
use App\Enums\IssueSource;
use App\Enums\TaskStatus;
use App\Enums\WorkShiftStatus;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Unit;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;

class BuildDashboardStatsAction
{
    public function __construct(
        private CountTimePresenceAttentionAction $countTimeAttention,
        private CountPendingAbsenceRequestsAction $countPendingAbsence,
        private CountWorkerAdminFollowUpAction $countWorkerFollowUp,
        private ListLocationStaffingShortfallsAction $staffingShortfalls,
    ) {}

    /**
     * @param  list<int>|null  $actorLocationIds  null = alle locaties
     */
    public function handle(int $tenantId, bool $hasTimeModule, bool $hasIotModule, bool $workMenuIssuesTasksEnabled = true, ?array $actorLocationIds = null): DashboardStatsData
    {
        $presentNow = null;
        $timeAttention = 0;
        $pendingAbsenceLeave = 0;
        $pendingAbsenceRecup = 0;
        $pendingAbsenceSick = 0;
        $pendingAbsenceOther = 0;
        $staffingShortfalls = 0;

        $timeRosterEnabled = $hasTimeModule && TimeModuleAccess::rosterEnabledForTenantId($tenantId);

        if ($hasTimeModule) {
            $presentNow = WorkShift::query()
                ->where('tenant_id', $tenantId)
                ->where('status', WorkShiftStatus::Open)
                ->whereDoesntHave('breaks', fn ($query) => $query->whereNull('ended_at'))
                ->when($actorLocationIds !== null, fn ($query) => $query->whereHas(
                    'presenceClockPoint',
                    fn ($point) => $point->whereIn('location_id', $actorLocationIds ?: [0]),
                ))
                ->count();

            $timeAttention = $this->countTimeAttention->handle($tenantId, $actorLocationIds);

            if ($timeRosterEnabled) {
                $pendingByKind = $this->countPendingAbsence->byKind($tenantId, $actorLocationIds);
                $pendingAbsenceLeave = $pendingByKind['leave'];
                $pendingAbsenceRecup = $pendingByKind['recup'];
                $pendingAbsenceSick = $pendingByKind['sick'];
                $pendingAbsenceOther = $pendingByKind['other'];

                $weekStart = now()->startOfWeek();
                $staffingShortfalls = $this->staffingShortfalls->handle(
                    $tenantId,
                    array_map(fn (int $i) => $weekStart->copy()->addDays($i)->toDateString(), range(0, 6)),
                    $actorLocationIds,
                )['total'];
            }
        }

        $iotAlarms = 0;
        if ($hasIotModule) {
            $iotAlarms = Issue::query()
                ->where('tenant_id', $tenantId)
                ->where('source', IssueSource::Iot)
                ->where('status', '!=', TaskStatus::Closed)
                ->when($actorLocationIds !== null, fn ($query) => $query->whereIn('location_id', $actorLocationIds ?: [0]))
                ->count();
        }

        $locationScope = fn ($query) => $query->when(
            $actorLocationIds !== null,
            fn ($scoped) => $scoped->whereIn('location_id', $actorLocationIds ?: [0]),
        );

        return new DashboardStatsData(
            locations: Location::query()
                ->where('tenant_id', $tenantId)
                ->when($actorLocationIds !== null, fn ($query) => $query->whereIn('id', $actorLocationIds ?: [0]))
                ->count(),
            units: Unit::query()->where('tenant_id', $tenantId)->tap($locationScope)->count(),
            newIssues: Issue::query()
                ->where('tenant_id', $tenantId)
                ->where('status', TaskStatus::New->value)
                ->tap($locationScope)
                ->count(),
            openTasks: Task::query()
                ->where('tenant_id', $tenantId)
                ->forApprovedIssue()
                ->whereIn('status', TaskStatus::openValues())
                ->when($actorLocationIds !== null, fn ($query) => $query->whereHas(
                    'issue',
                    fn ($issue) => $issue->whereIn('location_id', $actorLocationIds ?: [0]),
                ))
                ->count(),
            presentNow: $presentNow,
            pendingReview: Issue::query()
                ->where('tenant_id', $tenantId)
                ->pendingReview()
                ->tap($locationScope)
                ->count(),
            timeAttention: $timeAttention,
            pendingAbsenceRequests: $pendingAbsenceLeave + $pendingAbsenceRecup + $pendingAbsenceSick + $pendingAbsenceOther,
            pendingAbsenceLeave: $pendingAbsenceLeave,
            pendingAbsenceRecup: $pendingAbsenceRecup,
            pendingAbsenceSick: $pendingAbsenceSick,
            pendingAbsenceOther: $pendingAbsenceOther,
            workerFollowUp: $this->countWorkerFollowUp->handle($tenantId, $actorLocationIds),
            iotAlarms: $iotAlarms,
            staffingShortfalls: $staffingShortfalls,
            hasTimeModule: $hasTimeModule,
            hasIotModule: $hasIotModule,
            workMenuIssuesTasksEnabled: $workMenuIssuesTasksEnabled,
            timeRosterEnabled: $timeRosterEnabled,
        );
    }
}
