<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\User;

final class RestoreDashboardKpiAction
{
    public function handle(User $actor, string $key): void
    {
        if (! in_array($key, HideDashboardKpiAction::KEYS, true)) {
            return;
        }

        $hidden = [];
        foreach ($actor->dashboard_hidden_kpis ?? [] as $stored) {
            if (! is_string($stored) || $stored === $key || ! in_array($stored, HideDashboardKpiAction::KEYS, true)) {
                continue;
            }
            if (! in_array($stored, $hidden, true)) {
                $hidden[] = $stored;
            }
        }

        $actor->update(['dashboard_hidden_kpis' => $hidden]);
    }
}
