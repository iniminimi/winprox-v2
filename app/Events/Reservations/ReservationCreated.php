<?php

namespace App\Events\Reservations;

use App\Contracts\WebhookEvent;
use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCreated implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Reservation $reservation, public ?int $actorUserId = null) {}

    public function webhookEventName(): string
    {
        return 'reservation.created';
    }

    public function webhookPayload(): array
    {
        return self::payload($this->reservation, $this->actorUserId);
    }

    public function webhookTenantId(): int
    {
        return (int) $this->reservation->tenant_id;
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Reservation $reservation, ?int $actorUserId = null): array
    {
        $payload = [
            'id' => $reservation->id,
            'unit_id' => $reservation->unit_id,
            'guest_email' => $reservation->guest_email,
            'start_at' => optional($reservation->start_at)->toIso8601String(),
            'end_at' => optional($reservation->end_at)->toIso8601String(),
            'confirmed_at' => optional($reservation->confirmed_at)->toIso8601String(),
            'cancelled_at' => optional($reservation->cancelled_at)->toIso8601String(),
            'lifecycle' => $reservation->lifecycle()->value,
        ];

        if ($actorUserId !== null) {
            $payload['actor_user_id'] = $actorUserId;
            $payload['user_id'] = $actorUserId;
        }

        return $payload;
    }
}
