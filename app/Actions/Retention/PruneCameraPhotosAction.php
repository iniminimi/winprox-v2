<?php

declare(strict_types=1);

namespace App\Actions\Retention;

use App\Models\CameraPhoto;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;

final class PruneCameraPhotosAction
{
    /**
     * @return array{scanned: int, removed: int}
     */
    public function handle(bool $dryRun = false, ?CarbonInterface $now = null): array
    {
        $days = max(1, (int) config('camera.retention_days', 30));
        $cutoff = ($now ?? now())->copy()->subDays($days);
        $disk = Storage::disk(CameraPhoto::DISK);
        $stats = ['scanned' => 0, 'removed' => 0];

        CameraPhoto::query()
            ->withoutGlobalScopes()
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($dryRun, $disk, &$stats): void {
                foreach ($rows as $row) {
                    $stats['scanned']++;
                    $stats['removed']++;
                    if (! $dryRun) {
                        $disk->delete((string) $row->path);
                        $row->delete();
                    }
                }
            });

        return $stats;
    }
}
