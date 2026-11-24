<?php

namespace App\Actions\Retention;

use App\Models\AbsenceRequestPhoto;
use App\Support\AbsenceRequestPhotoStorage;
use App\Support\Time\AbsencePhotoRetention;
use Carbon\CarbonInterface;

/**
 * Wist foto's bij afwezigheidsaanvragen ouder dan de retentie (standaard 6 maanden).
 * De aanvraag zelf blijft staan.
 */
final class PruneAbsenceRequestPhotosAction
{
    public function __construct(private AbsenceRequestPhotoStorage $storage) {}

    /**
     * @return array{scanned: int, removed: int}
     */
    public function handle(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $cutoff = AbsencePhotoRetention::dropBefore($now);
        $stats = ['scanned' => 0, 'removed' => 0];

        AbsenceRequestPhoto::query()
            ->withoutGlobalScope('tenant')
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dryRun, &$stats): void {
                foreach ($rows as $row) {
                    $stats['scanned']++;
                    if (! $dryRun) {
                        $this->storage->delete((string) $row->path);
                        $row->delete();
                    }
                    $stats['removed']++;
                }
            });

        return $stats;
    }
}
