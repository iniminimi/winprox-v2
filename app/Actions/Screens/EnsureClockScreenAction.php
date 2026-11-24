<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Enums\ScreenRole;
use App\Models\ClockPoint;
use App\Models\Screen;
use App\Support\Screens\ScreenQr;

class EnsureClockScreenAction
{
    public function handle(ClockPoint $clockPoint): Screen
    {
        return Screen::query()->firstOrCreate(
            ['clock_point_id' => $clockPoint->id],
            [
                'tenant_id' => $clockPoint->tenant_id,
                'unit_id' => null,
                'role' => ScreenRole::Clock,
                'qr_window_seconds' => ScreenQr::WINDOW_CLOCK,
                'display_sound_enabled' => (bool) ($clockPoint->display_sound_enabled ?? true),
                'album_mode' => $clockPoint->album_mode ?? 'none',
                'album_days' => $clockPoint->album_days ?? 31,
            ],
        );
    }
}
