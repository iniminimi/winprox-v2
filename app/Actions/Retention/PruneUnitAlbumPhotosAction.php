<?php

declare(strict_types=1);

namespace App\Actions\Retention;

use App\Models\UnitAlbumPhoto;
use App\Support\UnitAlbumPhotoStorage;
use Illuminate\Support\Carbon;

/**
 * Wist albumfoto's (bestand + rij) ouder dan de retentie (standaard 7 dagen).
 */
final class PruneUnitAlbumPhotosAction
{
    public function __construct(private UnitAlbumPhotoStorage $storage) {}

    /**
     * @return array{scanned: int, removed: int}
     */
    public function handle(bool $dryRun = false, ?Carbon $now = null): array
    {
        $now ??= now();
        $days = max(1, (int) config('data_retention.unit_album_photos_days', 7));
        $cutoff = $now->copy()->subDays($days);
        $stats = ['scanned' => 0, 'removed' => 0];

        UnitAlbumPhoto::query()
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
