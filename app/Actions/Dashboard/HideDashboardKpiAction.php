<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\User;

final class HideDashboardKpiAction
{
    /** @var list<string> */
    public const KEYS = [
        'new_issues',
        'open_tasks',
        'present_now',
        'pending_review',
        'worker_follow_up',
        'staffing_shortfalls',
        'time_attention',
        'pending_absence',
        'iot_alarms',
    ];

    public function handle(User $actor, string $key): void
    {
        if (! in_array($key, self::KEYS, true)) {
            return;
        }

        $hidden = [];
        foreach ($actor->dashboard_hidden_kpis ?? [] as $stored) {
            if (is_string($stored) && in_array($stored, self::KEYS, true) && ! in_array($stored, $hidden, true)) {
                $hidden[] = $stored;
            }
        }

        if (in_array($key, $hidden, true)) {
            return;
        }

        $hidden[] = $key;
        $actor->update(['dashboard_hidden_kpis' => $hidden]);
    }
}
