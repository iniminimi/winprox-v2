<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\User;

final class RestoreDashboardIntentTileAction
{
    /**
     * @param  list<mixed>  $order
     */
    public function handle(User $actor, string $key, array $order): void
    {
        if (! in_array($key, BuildDashboardIntentHubAction::TILE_KEYS, true)) {
            return;
        }

        $hidden = [];
        foreach ($actor->dashboard_hidden_tiles ?? [] as $stored) {
            if (! is_string($stored) || $stored === $key || ! in_array($stored, BuildDashboardIntentHubAction::TILE_KEYS, true)) {
                continue;
            }
            if (! in_array($stored, $hidden, true)) {
                $hidden[] = $stored;
            }
        }

        $allowed = [...BuildDashboardIntentHubAction::TILE_KEYS, BuildDashboardIntentHubAction::BOX_KEY];
        $clean = [];
        foreach ($order as $item) {
            if (! is_string($item) || ! in_array($item, $allowed, true) || in_array($item, $hidden, true)) {
                continue;
            }
            if (! in_array($item, $clean, true)) {
                $clean[] = $item;
            }
        }

        if (! in_array($key, $clean, true)) {
            $boxAt = array_search(BuildDashboardIntentHubAction::BOX_KEY, $clean, true);
            if ($boxAt === false) {
                $clean[] = $key;
            } else {
                array_splice($clean, $boxAt, 0, [$key]);
            }
        }

        $actor->update([
            'dashboard_hidden_tiles' => $hidden,
            'dashboard_intent_order' => $clean,
        ]);
    }
}
