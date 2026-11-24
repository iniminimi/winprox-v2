<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Enums\ScreenRole;
use App\Models\ClockPoint;
use App\Models\Screen;

class SyncClockScreenAction
{
    public function linked(ClockPoint $point): void
    {
        if ($point->display_id === null || $point->display_paired_at === null) {
            return;
        }

        Screen::query()->updateOrCreate(
            ['clock_point_id' => $point->id],
            [
                'tenant_id' => $point->tenant_id,
                'unit_id' => null,
                'role' => ScreenRole::Clock,
                'display_id' => $point->display_id,
                'device_hint' => $point->display_device_hint,
                'paired_at' => $point->display_paired_at,
                'last_seen_at' => $point->display_last_seen_at,
            ],
        );
    }

    public function unlinked(ClockPoint $point): void
    {
        Screen::query()
            ->where('clock_point_id', $point->id)
            ->update([
                'device_hint' => null,
                'paired_at' => null,
                'last_seen_at' => null,
                'pairing_code' => null,
                'pairing_expires_at' => null,
            ]);
    }
}
