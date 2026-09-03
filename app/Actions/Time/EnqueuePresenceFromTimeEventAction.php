<?php

namespace App\Actions\Time;

use App\Enums\PresenceSourceEvent;
use App\Enums\PresenceSubmissionStatus;
use App\Jobs\SubmitPresenceSubmissionJob;
use App\Models\ClockPoint;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\WorkBreak;
use App\Models\WorkShift;
use App\Support\Time\TimeModuleAccess;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class EnqueuePresenceFromTimeEventAction
{
    public function __construct(
        private MapPresenceEventsAction $mapPresence,
    ) {}

    public function handle(
        PresenceSourceEvent $source,
        WorkShift $shift,
        ?WorkBreak $break = null,
        ?CarbonInterface $registrationAt = null,
    ): ?PresenceSubmission {
        $tenant = Tenant::query()->find($shift->tenant_id);
        if ($tenant === null || ! TimeModuleAccess::tenantHasModule($tenant)) {
            return null;
        }

        if (! $tenant->presenceComplianceEnabled()) {
            return null;
        }

        $scope = $tenant->presenceComplianceScope();
        if ($scope === null || ! $scope->isAvailable()) {
            return null;
        }

        $at = $registrationAt ?? now();
        $presenceType = $this->mapPresence->handle($source, $scope);

        $clockPointId = $shift->currentClockPointId();
        $clockPoint = ClockPoint::query()->find($clockPointId);
        $locationId = $clockPoint?->location_id !== null ? (int) $clockPoint->location_id : null;

        $submission = DB::transaction(function () use ($shift, $break, $source, $presenceType, $scope, $at, $clockPointId, $locationId) {
            return PresenceSubmission::create([
                'tenant_id' => $shift->tenant_id,
                'worker_id' => $shift->worker_id,
                'work_shift_id' => $shift->id,
                'work_break_id' => $break?->id,
                'clock_point_id' => $clockPointId,
                'location_id' => $locationId,
                'source_event' => $source,
                'presence_type' => $presenceType,
                'scope' => $scope,
                'registration_at' => $at,
                'status' => PresenceSubmissionStatus::Pending,
            ]);
        });

        SubmitPresenceSubmissionJob::dispatch((int) $submission->id);

        return $submission;
    }
}
