<?php

namespace App\Actions\Time;

use App\Actions\Notifications\CreateNotificationAction;
use App\Enums\WorkerNotificationType;
use App\Models\Tenant;
use App\Models\Worker;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Melding bij wijziging van een gepubliceerd blok — alleen het portaal-badge
 * kanaal is actief; $via is de seam waar later 'mail' bij kan zonder herbouw.
 *
 * Anti-spamregels (hard):
 * - per worker×dag max. één ongelezen roster_changed (CreateNotificationAction
 *   doet updateOrCreate op (worker,type,reference) en reset read_at),
 * - één melding per save-actie, niet per blok,
 * - alleen werkdagen vanaf vandaag,
 * - alleen bij wijzigingen aan published blokken (caller levert die paren aan).
 */
class NotifyWorkersRosterChangedAction
{
    public function __construct(private CreateNotificationAction $createNotification) {}

    /**
     * @param  list<array{worker_id: int, date: string}>  $workerDates
     * @param  list<string>  $via
     */
    public function handle(Tenant $tenant, array $workerDates, array $via = ['portal'], ?Carbon $now = null): int
    {
        $today = ($now ?? now())->toDateString();

        $pairs = [];
        foreach ($workerDates as $entry) {
            $workerId = (int) ($entry['worker_id'] ?? 0);
            $date = (string) ($entry['date'] ?? '');
            if ($workerId === 0 || $date === '' || $date < $today) {
                continue;
            }
            $pairs[$workerId.'|'.$date] = [$workerId, $date];
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
        foreach ($pairs as [$workerId, $date]) {
            $worker = $workers->get($workerId);
            if (! $worker instanceof Worker) {
                continue;
            }

            foreach ($via as $channel) {
                match ($channel) {
                    'portal' => $this->createNotification->handle(
                        $tenant,
                        $worker,
                        WorkerNotificationType::RosterChanged,
                        $date,
                    ),
                    default => throw new InvalidArgumentException('unknown_notification_channel'),
                };
            }
            $count++;
        }

        return $count;
    }
}
