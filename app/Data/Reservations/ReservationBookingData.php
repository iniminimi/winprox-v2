<?php

namespace App\Data\Reservations;

readonly class ReservationBookingData
{
    public function __construct(
        public string $guestFirstName,
        public string $guestLastName,
        public string $guestEmail,
        public string $startAt,
        public string $endAt,
        public ?int $workerId = null,
        public bool $autoConfirm = false,
        public ?int $createdByUserId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromValidated(array $input, bool $autoConfirm = false, ?int $createdByUserId = null, ?int $workerId = null): self
    {
        return new self(
            guestFirstName: trim((string) $input['guest_first_name']),
            guestLastName: trim((string) $input['guest_last_name']),
            guestEmail: strtolower(trim((string) $input['guest_email'])),
            startAt: (string) $input['start_at'],
            endAt: (string) $input['end_at'],
            workerId: $workerId ?? (isset($input['worker_id']) ? (int) $input['worker_id'] : null),
            autoConfirm: $autoConfirm,
            createdByUserId: $createdByUserId,
        );
    }
}
