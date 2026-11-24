<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Models\Screen;
use App\Models\Unit;
use App\Support\Screens\ScreenQr;

class ResolveUnitScreenTokenAction
{
    public function handle(string $token): ?Unit
    {
        if (strlen($token) !== 40) {
            return null;
        }

        $screen = Screen::query()
            ->withoutGlobalScopes()
            ->where('display_id', substr($token, 0, 24))
            ->whereNotNull('unit_id')
            ->whereNull('clock_point_id')
            ->first();

        if ($screen === null || ! ScreenQr::matches($screen, substr($token, 24))) {
            return null;
        }

        return Unit::query()->withoutGlobalScopes()->find($screen->unit_id);
    }
}
