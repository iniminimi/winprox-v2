<?php

namespace App\Actions\Time;

use App\Enums\ClockSource;
use App\Enums\PresenceSourceEvent;
use App\Events\Time\WorkVisitEnded;
use App\Models\WorkVisit;
use App\Models\Worker;
use App\Support\Time\TimeModuleAccess;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EndWorkVisitAction
{
    public function __construct(
        private EnqueuePresenceFromTimeEventAction $enqueuePresence,
    ) {}

    public function handle(
        Worker $worker,
        bool $required = true,
        ?float $latitude = null,
        ?float $longitude = null,
        ClockSource $source = ClockSource::Gps,
    ): ?WorkVisit {
        TimeModuleAccess::assertEnabledForTenantId((int) $worker->tenant_id);

        return DB::transaction(function () use ($worker, $required, $latitude, $longitude, $source) {
            Worker::query()->whereKey($worker->id)->lockForUpdate()->first();

            $visit = WorkVisit::query()
                ->where('worker_id', $worker->id)
                ->open()
                ->lockForUpdate()
                ->first();

            if ($visit === null) {
                if ($required) {
                    throw new InvalidArgumentException('visit_not_open');
                }

                return null;
            }

            $visit->update([
                'ended_at' => now(),
                'end_latitude' => $latitude,
                'end_longitude' => $longitude,
            ]);

            $visit = $visit->fresh(['unit', 'location', 'workShift']);

            event(new WorkVisitEnded($visit));
            $this->enqueuePresence->handle(PresenceSourceEvent::VisitEnd, $visit->workShift, visit: $visit);

            return $visit;
        });
    }
}
