<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Data\Dashboard\DashboardStatsData;
use App\Enums\IssueSource;
use App\Enums\TaskStatus;
use App\Enums\WorkShiftStatus;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Task;
use App\Models\Unit;
use App\Models\WorkShift;

class BuildDashboardStatsAction
{
    public function __construct(
        private CountTimePresenceAttentionAction $countTimeAttention,
    ) {}

    public function handle(int $tenantId, bool $hasTimeModule, bool $hasIotModule): DashboardStatsData
    {
        $presentNow = null;
        $timeAttention = 0;

        if ($hasTimeModule) {
            $presentNow = WorkShift::query()
                ->where('tenant_id', $tenantId)
                ->where('status', WorkShiftStatus::Open)
                ->whereDoesntHave('breaks', fn ($query) => $query->whereNull('ended_at'))
                ->count();

            $timeAttention = $this->countTimeAttention->handle($tenantId);
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
            iotAlarms: $iotAlarms,
            hasTimeModule: $hasTimeModule,
            hasIotModule: $hasIotModule,
        );
    }
}
