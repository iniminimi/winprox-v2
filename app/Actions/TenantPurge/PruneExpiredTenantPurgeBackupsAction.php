<?php

namespace App\Actions\TenantPurge;

use App\Enums\TenantPurgeStatus;
use App\Models\TenantPurgeRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Verwijdert verlopen SQL-snapshots na de retentieperiode.
 */
final class PruneExpiredTenantPurgeBackupsAction
{
    /**
     * @return array{scanned: int, deleted: int}
     */
    public function handle(bool $dryRun = false, ?Carbon $now = null): array
    {
        $now ??= now();
        $stats = ['scanned' => 0, 'deleted' => 0];
        $disk = Storage::disk('local');

        TenantPurgeRequest::query()
            ->where('status', TenantPurgeStatus::Completed)
            ->whereNotNull('backup_path')
            ->whereNotNull('backup_expires_at')
            ->where('backup_expires_at', '<=', $now)
            ->orderBy('id')
            ->each(function (TenantPurgeRequest $request) use ($dryRun, $disk, &$stats): void {
                $stats['scanned']++;
                $path = (string) $request->backup_path;

                if (! $dryRun) {
                    if ($path !== '' && $disk->exists($path)) {
                        $disk->delete($path);
                    }
                    $request->backup_path = null;
                    $request->save();
                }

                $stats['deleted']++;
            });

        return $stats;
    }
}
