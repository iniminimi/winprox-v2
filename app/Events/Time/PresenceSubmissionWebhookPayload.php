<?php

namespace App\Events\Time;

use App\Models\PresenceSubmission;

/**
 * Stabiele, NISS-vrije webhook-payload voor CIAO presence-submissions.
 */
final class PresenceSubmissionWebhookPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function from(PresenceSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'worker_id' => $submission->worker_id,
            'work_shift_id' => $submission->work_shift_id,
            'work_break_id' => $submission->work_break_id,
            'clock_point_id' => $submission->clock_point_id,
            'location_id' => $submission->location_id,
            'source_event' => $submission->source_event->value,
            'presence_type' => $submission->presence_type->value,
            'scope' => $submission->scope->value,
            'status' => $submission->status->value,
            'registration_at' => $submission->registration_at?->toIso8601String(),
            'submitted_at' => $submission->submitted_at?->toIso8601String(),
            'rsz_id' => $submission->rsz_id,
            'rsz_validity' => $submission->rsz_validity,
            'error_message' => $submission->error_message,
        ];
    }
}
