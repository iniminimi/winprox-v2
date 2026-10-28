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
        $staffingShortfalls = 0;

        $timeRosterEnabled = $hasTimeModule && TimeModuleAccess::rosterEnabledForTenantId($tenantId);

        if ($hasTimeModule) {
            $presentNow = WorkShift::query()
                ->where('tenant_id', $tenantId)
                ->where('status', WorkShiftStatus::Open)
                ->whereDoesntHave('breaks', fn ($query) => $query->whereNull('ended_at'))
                ->count();

            $timeAttention = $this->countTimeAttention->handle($tenantId);

            if ($timeRosterEnabled) {
                $pendingByKind = $this->countPendingAbsence->byKind($tenantId);
                $pendingAbsenceLeave = $pendingByKind['leave'];
                $pendingAbsenceRecup = $pendingByKind['recup'];

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
                ->count();
        }

        return new DashboardStatsData(
            locations: Location::query()->where('tenant_id', $tenantId)->count(),
            units: Unit::query()->where('tenant_id', $tenantId)->count(),
            newIssues: Issue::query()
                ->where('tenant_id', $tenantId)
                ->where('status', TaskStatus::New->value)
                ->count(),
            openTasks: Task::query()
                ->where('tenant_id', $tenantId)
                ->forApprovedIssue()
                ->whereIn('status', TaskStatus::openValues())
                ->count(),
            presentNow: $presentNow,
            pendingReview: Issue::query()
                ->where('tenant_id', $tenantId)
                ->pendingReview()
                ->count(),
            timeAttention: $timeAttention,
            pendingAbsenceRequests: $pendingAbsenceLeave + $pendingAbsenceRecup,
            pendingAbsenceLeave: $pendingAbsenceLeave,
            pendingAbsenceRecup: $pendingAbsenceRecup,
            workerFollowUp: $this->countWorkerFollowUp->handle($tenantId),
            iotAlarms: $iotAlarms,
            staffingShortfalls: $staffingShortfalls,
            hasTimeModule: $hasTimeModule,
            hasIotModule: $hasIotModule,
            workMenuIssuesTasksEnabled: $workMenuIssuesTasksEnabled,
            timeRosterEnabled: $timeRosterEnabled,
        );
    }
}
