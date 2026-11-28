<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Models\Screen;

class RecordUnitScreenPingAction
{
    public function handle(Screen $screen): Screen
    {
        if ($screen->last_seen_at === null || $screen->last_seen_at->lessThan(now()->subSeconds(30))) {
            $screen->update(['last_seen_at' => now()]);
        }

        return $screen->fresh();
    }
}
