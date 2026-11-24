<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Enums\ScreenRole;
use App\Models\Screen;
use App\Models\Unit;
use App\Support\Screens\ScreenQr;
use Illuminate\Validation\ValidationException;

class EnsureUnitScreenAction
{
    public function handle(Unit $unit, int $tenantId): Screen
    {
        if ((int) $unit->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'unit' => [__('screen.errors.unit_invalid')],
            ]);
        }

        return Screen::query()->firstOrCreate(
            ['unit_id' => $unit->id],
            [
                'tenant_id' => $tenantId,
                'role' => ScreenRole::Sense,
                'qr_window_seconds' => ScreenQr::WINDOW_UNIT,
                'display_sound_enabled' => true,
                'album_mode' => 'none',
                'album_days' => 31,
            ],
        );
    }
}
