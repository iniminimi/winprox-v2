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
use App\Models\WorkVisit;
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
        ?WorkVisit $visit = null,
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

        if ($tenant->allowsGpsWorkVisits() && in_array($source, [
            PresenceSourceEvent::ClockIn,
            PresenceSourceEvent::ClockOut,
        ], true)) {
            return null;
        }

        // Tijdens GPS-werkbezoek: pauze OUT/IN hoort bij de klantlocatie (DDT), niet bij Clock Point.
        if ($visit === null && in_array($source, [
            PresenceSourceEvent::BreakStart,
            PresenceSourceEvent::BreakEnd,
        ], true)) {
            $visit = WorkVisit::query()
                ->where('work_shift_id', $shift->id)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->first();
        }

        $at = $registrationAt ?? now();
        $presenceType = $this->mapPresence->handle($source, $scope);

        $clockPointId = $shift->currentClockPointId();
        $clockPoint = ClockPoint::query()->find($clockPointId);
        $locationId = $visit?->location_id !== null
            ? (int) $visit->location_id
            : ($clockPoint?->location_id !== null ? (int) $clockPoint->location_id : null);
        $unitId = $visit?->unit_id !== null ? (int) $visit->unit_id : null;

        $submission = DB::transaction(function () use ($shift, $break, $visit, $source, $presenceType, $scope, $at, $clockPointId, $locationId, $unitId) {
            return PresenceSubmission::create([
                'tenant_id' => $shift->tenant_id,
                'worker_id' => $shift->worker_id,
                'work_shift_id' => $shift->id,
                'work_break_id' => $break?->id,
                'work_visit_id' => $visit?->id,
                'clock_point_id' => $clockPointId,
                'location_id' => $locationId,
                'unit_id' => $unitId,
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
