<?php

namespace App\Enums;

use App\Data\Notifications\WorkerNotificationPortalTarget;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Harde mapping: type → betekenis van reference_id → Clock Point-navigatie.
 *
 * Nieuwe types krijgen een case + formaat + portalTarget. Niet stilzwijgend
 * een andere reference_id-vorm gebruiken.
 *
 * | Type              | reference_id                                      | Niet                         | Navigatie                                      |
 * |-------------------|---------------------------------------------------|------------------------------|------------------------------------------------|
 * | roster_published  | ISO-datum Y-m-d van periode-start ($dates[0])     | planned_shifts.id, worker-id | Clock Point → Mijn rooster, week van die datum |
 * | roster_changed    | ISO-datum Y-m-d van de gewijzigde werkdag         | planned_shifts.id, worker-id | Clock Point → Mijn rooster, dag in die maand   |
 */
enum WorkerNotificationType: string
{
    case RosterPublished = 'roster_published';
    case RosterChanged = 'roster_changed';

    public function assertReferenceId(string $referenceId): void
    {
        if ($this === self::RosterPublished || $this === self::RosterChanged) {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $referenceId)) {
                throw new InvalidArgumentException('time.schedule.errors.invalid_notification_reference');
            }

            $parsed = Carbon::createFromFormat('Y-m-d', $referenceId);
            if ($parsed === false || $parsed->toDateString() !== $referenceId) {
                throw new InvalidArgumentException('time.schedule.errors.invalid_notification_reference');
            }

            return;
        }

        throw new InvalidArgumentException('time.schedule.errors.invalid_notification_reference');
    }

    public function portalTarget(string $referenceId): WorkerNotificationPortalTarget
    {
        $this->assertReferenceId($referenceId);

        return match ($this) {
            self::RosterPublished, self::RosterChanged => new WorkerNotificationPortalTarget(
                screen: 'schedule',
                cursor: $referenceId,
            ),
        };
    }
}
