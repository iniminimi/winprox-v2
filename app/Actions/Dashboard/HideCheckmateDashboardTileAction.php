<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\User;

final class HideCheckmateDashboardTileAction
{
    /** @var list<string> */
    public const KEYS = [
        'add_customer',
        'add_worker',
        'presence',
        'present_now',
        'visits_today',
        'workers',
        'seats',
        'customers',
        'clock_point_qr',
    ];

    public function handle(User $actor, string $key): void
    {
        if (! in_array($key, self::KEYS, true)) {
            return;
        }

        $hidden = [];
        foreach ($actor->dashboard_hidden_checkmate_tiles ?? [] as $stored) {
            if (is_string($stored) && in_array($stored, self::KEYS, true) && ! in_array($stored, $hidden, true)) {
                $hidden[] = $stored;
            }
        }

        if (in_array($key, $hidden, true)) {
            return;
        }

        $hidden[] = $key;
        $actor->update(['dashboard_hidden_checkmate_tiles' => $hidden]);
    }
}
