<?php

namespace App\Actions\Time;

use App\Data\Time\CopyRosterWeeksData;
use App\Data\Time\CopyWeekData;
use App\Exceptions\RosterValidationException;
use App\Models\PlannedShift;
use App\Models\Tenant;

class CopyWeekAction
{
    public function __construct(private CopyRosterWeeksAction $weeks) {}

    /**
     * Enkelvoudige doelweek — dunne laag boven CopyRosterWeeksAction die de
     * rapportstatussen weer als uitzonderingen gooit (API-back-compat).
     *
     * @return list<PlannedShift>
     */
    public function handle(Tenant $tenant, CopyWeekData $data, ?int $actorUserId): array
    {
        $report = $this->weeks->handle(
            $tenant,
            new CopyRosterWeeksData($data->sourceWeekStart, $data->targetWeekStart, 1, $data->workerIds),
            $actorUserId,
        );

        $week = $report['weeks'][0];
        if ($week['status'] === CopyRosterWeeksAction::STATUS_SAME_WEEK) {
            throw new RosterValidationException('time.schedule.errors.copy_same_week');
        }
        if ($week['status'] === CopyRosterWeeksAction::STATUS_OCCUPIED) {
            throw new RosterValidationException('time.schedule.errors.copy_conflict');
        }
        if ($week['status'] === CopyRosterWeeksAction::STATUS_FAILED) {
            throw new RosterValidationException($week['message'] ?? 'time.schedule.errors.copy_conflict');
        }

        return $week['shifts'];
    }
}
