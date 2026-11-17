<?php

namespace App\Enums;

use App\Data\Notifications\WorkerNotificationPortalTarget;
use App\Support\Push\WorkerPushScreen;
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
 * | roster_corrected  | Y-m-d:planned_shift_id van het gecorrigeerde blok | willekeurige string          | Clock Point → Mijn rooster, dag in die maand   |
 * | team_document     | team_documents.id                                 | willekeurige string          | Clock Point → documenten                       |
 * | team_announcement | team_announcements.id                             | willekeurige string          | Clock Point → mededelingen                     |
 * | absence_approved  | absence_requests.id                               | willekeurige string          | Clock Point → verlof                           |
 * | clock_out_reminder| work_shifts.id                                    | willekeurige string          | Clock Point (nog ingeklokt)                    |
 */
enum WorkerNotificationType: string
{
    case RosterPublished = 'roster_published';
    case RosterChanged = 'roster_changed';
    case RosterCorrected = 'roster_corrected';
    case TeamDocument = 'team_document';
    case TeamAnnouncement = 'team_announcement';
    case AbsenceApproved = 'absence_approved';
    case ClockOutReminder = 'clock_out_reminder';

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

        if ($this === self::RosterCorrected) {
            // Datum bepaalt de portaal-navigatie; het blok-id houdt afzonderlijke
            // correcties voor dezelfde worker uit elkaar (dedup-sleutel).
            if (! preg_match('/^\d{4}-\d{2}-\d{2}:\d+$/', $referenceId)) {
                throw new InvalidArgumentException('time.schedule.errors.invalid_notification_reference');
            }

            return;
        }

        if (in_array($this, [self::TeamDocument, self::TeamAnnouncement, self::AbsenceApproved, self::ClockOutReminder], true)) {
            if (! preg_match('/^[1-9]\d{0,18}$/', $referenceId)) {
                throw new InvalidArgumentException('time.schedule.errors.invalid_notification_reference');
            }

            return;
        }

        throw new InvalidArgumentException('time.schedule.errors.invalid_notification_reference');
    }

    public function openScreen(): string
    {
        return match ($this) {
            self::RosterPublished, self::RosterChanged, self::RosterCorrected => WorkerPushScreen::SCHEDULE,
            self::TeamDocument => WorkerPushScreen::DOCUMENTS,
            self::TeamAnnouncement => WorkerPushScreen::ANNOUNCEMENTS,
            self::AbsenceApproved => WorkerPushScreen::ABSENCE,
            self::ClockOutReminder => '',
        };
    }

    public function portalTarget(string $referenceId): WorkerNotificationPortalTarget
    {
        $this->assertReferenceId($referenceId);

        return match ($this) {
            self::RosterPublished, self::RosterChanged => new WorkerNotificationPortalTarget(
                screen: 'schedule',
                cursor: $referenceId,
            ),
            self::RosterCorrected => new WorkerNotificationPortalTarget(
                screen: 'schedule',
                cursor: str($referenceId)->before(':')->toString(),
            ),
            self::TeamDocument => new WorkerNotificationPortalTarget(screen: WorkerPushScreen::DOCUMENTS, cursor: $referenceId),
            self::TeamAnnouncement => new WorkerNotificationPortalTarget(screen: WorkerPushScreen::ANNOUNCEMENTS, cursor: $referenceId),
            self::AbsenceApproved => new WorkerNotificationPortalTarget(screen: WorkerPushScreen::ABSENCE, cursor: $referenceId),
            self::ClockOutReminder => new WorkerNotificationPortalTarget(screen: 'home', cursor: $referenceId),
        };
    }
}
