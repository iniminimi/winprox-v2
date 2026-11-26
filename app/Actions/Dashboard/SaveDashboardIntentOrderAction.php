<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\User;

final class SaveDashboardIntentOrderAction
{
    /**
     * @param  list<mixed>  $keys
     */
    public function handle(User $actor, array $keys): void
    {
        $clean = [];
        foreach ($keys as $key) {
            if (! is_string($key) || ! in_array($key, [...BuildDashboardIntentHubAction::TILE_KEYS, BuildDashboardIntentHubAction::BOX_KEY], true)) {
                continue;
            }
            if (in_array($key, $clean, true)) {
                continue;
            }
            $clean[] = $key;
        }

        if ($clean === [] || $actor->dashboard_intent_order === $clean) {
            return;
        }

        $actor->update(['dashboard_intent_order' => $clean]);
    }
}
