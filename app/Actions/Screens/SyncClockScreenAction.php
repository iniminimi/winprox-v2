<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Enums\ScreenRole;
use App\Models\ClockPoint;
use App\Models\Screen;
use App\Support\Screens\ScreenQr;

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

        $this->copySettings($point->fresh());
    }

    public function copySettings(ClockPoint $point): void
    {
        $screen = Screen::query()->where('clock_point_id', $point->id)->first();
        if ($screen === null) {
            return;
        }

        $screen->update([
            'display_id' => $point->display_id ?? $screen->display_id,
            'display_secret' => $point->display_secret,
            'qr_window_seconds' => ScreenQr::WINDOW_CLOCK,
            'display_on_from' => $point->display_on_from,
            'display_on_until' => $point->display_on_until,
            'display_sound_enabled' => (bool) ($point->display_sound_enabled ?? true),
            'album_mode' => $point->album_mode ?? 'none',
            'album1_from' => $point->album1_from,
            'album1_until' => $point->album1_until,
            'album2_from' => $point->album2_from,
            'album2_until' => $point->album2_until,
            'album_days' => $point->album_days ?? 31,
        ]);
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
