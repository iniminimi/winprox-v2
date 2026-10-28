<?php

declare(strict_types=1);

namespace App\Actions\Retention;

use App\Models\RosterRestorePoint;
use Illuminate\Support\Carbon;

/**
 * Verwijdert uurrooster-herstelpunten ouder dan de retentie (standaard 30 dagen).
 */
final class PruneRosterRestorePointsAction
{
    /**
     * @return array{scanned: int, removed: int}
     */
    public function handle(bool $dryRun = false, ?Carbon $now = null): array
    {
        $now ??= now();
        $days = max(1, (int) config('data_retention.roster_restore_points_days', 30));
        $cutoff = $now->copy()->subDays($days);
        $stats = ['scanned' => 0, 'removed' => 0];

        RosterRestorePoint::query()
            ->withoutGlobalScope('tenant')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dryRun, &$stats): void {
                foreach ($rows as $row) {
                    $stats['scanned']++;
                    if (! $dryRun) {
                        $row->delete();
                    }
                    $stats['removed']++;
                }
            });

        return $stats;
    }
}
