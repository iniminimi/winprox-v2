<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\User;

final class HideDashboardIntentTileAction
{
    public function handle(User $actor, string $key): void
    {
        if (! in_array($key, BuildDashboardIntentHubAction::TILE_KEYS, true)) {
            return;
        }

        $hidden = [];
        foreach ($actor->dashboard_hidden_tiles ?? [] as $stored) {
            if (is_string($stored) && in_array($stored, BuildDashboardIntentHubAction::TILE_KEYS, true) && ! in_array($stored, $hidden, true)) {
                $hidden[] = $stored;
            }
        }

        if (in_array($key, $hidden, true)) {
            return;
        }

        $hidden[] = $key;
        $actor->update(['dashboard_hidden_tiles' => $hidden]);
    }
}
