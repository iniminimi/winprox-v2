<?php

namespace App\Actions\Time;

use App\Actions\Notifications\CreateNotificationAction;
use App\Enums\WorkerNotificationType;
use App\Models\Tenant;
use App\Models\Worker;
use InvalidArgumentException;

/**
 * Melding bij een geauditeerde correctie op een historisch roosterblok (pad B).
 *
 * Bewust NIET gefilterd op datum — een correctie raakt per definitie verleden
 * dagen en de betrokken worker moet die zien, in tegenstelling tot gewone
 * roosterwijzigingen (NotifyWorkersRosterChangedAction filtert verleden dagen
 * wel weg, omdat die melding bedoeld is als "je komende planning is anders").
 *
 * Dedup-sleutel per worker×blok: reference "{Y-m-d}:{planned_shift_id}" — twee
 * afzonderlijke correcties op verschillende blokken smelten niet samen, een
 * herhaalde correctie van hetzelfde blok bumpt de bestaande melding.
 */
class NotifyWorkersOfHistoricalRosterCorrectionAction
{
    public function __construct(private CreateNotificationAction $createNotification) {}

    /**
     * @param  list<array{worker_id: int, planned_shift_id: int, date: string}>  $records
     * @param  list<string>  $via
     */
    public function handle(Tenant $tenant, array $records, array $via = ['portal']): int
    {
        $pairs = [];
        foreach ($records as $entry) {
            $workerId = (int) ($entry['worker_id'] ?? 0);
            $shiftId = (int) ($entry['planned_shift_id'] ?? 0);
            $date = (string) ($entry['date'] ?? '');
            if ($workerId === 0 || $shiftId === 0 || $date === '') {
                continue;
            }
            $pairs[$workerId.'|'.$shiftId] = [$workerId, $shiftId, $date];
        }

        if ($pairs === []) {
            return 0;
        }

        $workers = Worker::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', array_column($pairs, 0))
            ->get()
            ->keyBy('id');

        $count = 0;
        foreach ($pairs as [$workerId, $shiftId, $date]) {
            $worker = $workers->get($workerId);
            if (! $worker instanceof Worker) {
                continue;
            }

            foreach ($via as $channel) {
                match ($channel) {
                    'portal' => $this->createNotification->handle(
                        $tenant,
                        $worker,
                        WorkerNotificationType::RosterCorrected,
                        $date.':'.$shiftId,
                    ),
                    default => throw new InvalidArgumentException('unknown_notification_channel'),
                };
            }
            $count++;
        }

        return $count;
    }
}
