<?php

declare(strict_types=1);

namespace App\Actions\Retention;

use App\Enums\PresenceSubmissionStatus;
use App\Models\PresenceSubmission;
use Illuminate\Support\Carbon;

/**
 * Verwijdert geslaagde CIAO-inzendingen ouder dan de retentie (default 12 maanden).
 * Mislukt / in wachtrij / overgeslagen blijven staan voor herprobeer en diagnose.
 */
final class PrunePresenceSubmissionsAction
{
    /**
     * @return array{scanned: int, removed: int}
     */
    public function handle(bool $dryRun = false, ?Carbon $now = null): array
    {
        $now ??= now();
        $months = max(1, (int) config('data_retention.presence_submissions_months', 12));
        $cutoff = $now->copy()->subMonths($months);

        $stats = ['scanned' => 0, 'removed' => 0];

        PresenceSubmission::query()
            ->withoutGlobalScopes()
            ->where('status', PresenceSubmissionStatus::Submitted)
            ->where('registration_at', '<', $cutoff)
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
