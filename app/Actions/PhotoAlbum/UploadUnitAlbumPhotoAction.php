<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\Worker;
use App\Support\Audit\AuditRecorder;
use App\Support\UnitAlbumPhotoStorage;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Voegt één foto toe aan het album van een unit (unit-QR-portaal).
 * Maximum per unit per kalenderdag: config('unit_photo_album.max_photos_per_day').
 * Bestand is client-side gecomprimeerd — geen server-resize (WINPROX_RULES.md §7).
 */
final class UploadUnitAlbumPhotoAction
{
    public function __construct(
        private AuditRecorder $audit,
        private UnitAlbumPhotoStorage $storage,
    ) {}

    public function handleForWorker(int $unitId, UploadedFile $file, int $tenantId, Worker $worker, ?CarbonInterface $now = null): UnitAlbumPhoto
    {
        $unit = Unit::query()->where('tenant_id', $tenantId)->find($unitId);
        if ($unit === null) {
            throw new InvalidArgumentException('unit_unavailable');
        }

        return $this->handle($unit, $file, $tenantId, $worker, $now);
    }

    public function handle(
        Unit $unit,
        UploadedFile $file,
        int $tenantId,
        Worker $worker,
        ?CarbonInterface $now = null,
    ): UnitAlbumPhoto {
        if ((int) $unit->tenant_id !== $tenantId || (int) $worker->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (! $unit->allowsPhotoAlbum()) {
            throw new InvalidArgumentException('album_disabled');
        }

        if (! $worker->canClockAt((int) $unit->location_id)) {
            throw new InvalidArgumentException('album_unit_not_allowed');
        }

        $day = ($now ?? now())->toDateString();

        if ($this->countForDay($unit, $tenantId, $day) >= self::maxPerDay()) {
            throw new InvalidArgumentException('album_day_full');
        }

        $path = $this->storage->store($file, $tenantId, (int) $unit->id, $day);

        try {
            return DB::transaction(function () use ($unit, $tenantId, $worker, $day, $path): UnitAlbumPhoto {
                $photo = UnitAlbumPhoto::query()->create([
                    'tenant_id' => $tenantId,
                    'unit_id' => $unit->id,
                    'worker_id' => $worker->id,
                    'path' => $path,
                    'taken_on' => $day,
                ]);

                $this->audit->record(
                    userId: null,
                    tenantId: $tenantId,
                    action: 'unit_album.photo_uploaded',
                    modelType: UnitAlbumPhoto::class,
                    modelId: (int) $photo->id,
                    payload: [
                        'unit_id' => (int) $unit->id,
                        'worker_id' => (int) $worker->id,
                        'taken_on' => $day,
                    ],
                );

                return $photo;
            });
        } catch (\Throwable $e) {
            $this->storage->delete($path);
            throw $e;
        }
    }

    public static function maxPerDay(): int
    {
        return max(1, (int) config('unit_photo_album.max_photos_per_day', 24));
    }

    public function countForDay(Unit $unit, int $tenantId, string $day): int
    {
        return UnitAlbumPhoto::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unit->id)
            ->whereDate('taken_on', $day)
            ->count();
    }
}
