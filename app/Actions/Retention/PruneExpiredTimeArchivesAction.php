<?php

namespace App\Actions\Retention;

use App\Models\TenantTimeArchive;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Verwijdert zipbestanden waarvan de downloadtermijn voorbij is.
 * Jaarbestanden na 3 jaar, fotobestanden na de maand erna.
 */
final class PruneExpiredTimeArchivesAction
{
    /**
     * @return array{scanned: int, removed: int}
     */
    public function handle(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $now = $now ?? now();
        $stats = ['scanned' => 0, 'removed' => 0];
        $disk = Storage::disk(TenantTimeArchive::DISK);

        TenantTimeArchive::query()
            ->withoutGlobalScopes()
            ->where('available_until', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($dryRun, $disk, &$stats): void {
                foreach ($rows as $row) {
                    $stats['scanned']++;
                    if ($dryRun) {
                        $stats['removed']++;

                        continue;
                    }
                    $path = (string) $row->path;
                    if ($path !== '' && str_starts_with($path, TenantTimeArchive::DIRECTORY.'/'.$row->tenant_id.'/')) {
                        $disk->delete($path);
                    }
                    $row->delete();
                    $stats['removed']++;
                }
            });

        return $stats;
    }
}
