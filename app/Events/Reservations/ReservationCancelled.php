<?php

namespace App\Events\Reservations;

use App\Contracts\WebhookEvent;
use App\Models\Reservation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled implements WebhookEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(public Reservation $reservation, public ?int $actorUserId = null) {}

    public function webhookEventName(): string
    {
        return 'reservation.cancelled';
    }

    public function webhookPayload(): array
    {
        return ReservationCreated::payload($this->reservation, $this->actorUserId);
    }

    public function webhookTenantId(): int
    {
        return (int) $this->reservation->tenant_id;
    }
}
