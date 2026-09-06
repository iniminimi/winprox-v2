<?php

namespace App\Data\Time;

use App\Models\WorkShift;

final class TimeRosterPerson
{
    public function __construct(
        public int $workerId,
        public string $displayName,
        public string $firstName,
        public string $lastName,
        public string $teamName,
        public string $locationName,
        public string $clockPointName,
        public string $clockedInAt,
        public bool $onBreak,
        public string $roleKey,
    ) {}

    public static function fromOpenShift(WorkShift $shift): self
    {
        $worker = $shift->worker;
        $user = $worker?->user;
        $clockPoint = $shift->presenceClockPoint ?? $shift->clockInClockPoint;
        $location = $clockPoint?->location;

        $roleKey = 'worker';
        if ($user?->isAdmin()) {
            $roleKey = 'admin';
        } elseif ($user?->isEmployee()) {
            $roleKey = 'employee';
        }

        return new self(
            workerId: (int) $shift->worker_id,
            displayName: $worker?->displayName() ?? '—',
            firstName: (string) ($worker?->first_name ?? ''),
            lastName: (string) ($worker?->last_name ?? ''),
            teamName: $shift->team?->localizedName() ?? '',
            locationName: $location?->localizedName() ?? $location?->name ?? __('time.presence.unknown_location'),
            clockPointName: $clockPoint?->name ?? '',
            clockedInAt: $shift->clock_in_at?->timezone(config('app.timezone'))->format('H:i') ?? '',
            onBreak: $shift->openBreak !== null,
            roleKey: $roleKey,
        );
    }
}
