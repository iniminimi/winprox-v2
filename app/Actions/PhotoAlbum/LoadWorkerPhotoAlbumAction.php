<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\Worker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Foto's van vandaag voor de unit die de uitvoerder net via de unit-QR opende.
 */
final class LoadWorkerPhotoAlbumAction
{
    /**
     * @return array{photos: Collection<int, UnitAlbumPhoto>, previewUrls: array<int, string>, count: int, max: int}
     */
    public function handle(Worker $worker, Unit $unit): array
    {
        $empty = [
            'photos' => collect(),
            'previewUrls' => [],
            'count' => 0,
            'max' => UploadUnitAlbumPhotoAction::maxPerDay(),
        ];

        if ((int) $unit->tenant_id !== (int) $worker->tenant_id
            || ! $unit->allowsPhotoAlbum()
            || ! $worker->canClockAt($unit->location_id !== null ? (int) $unit->location_id : null)) {
            return $empty;
        }

        $photos = UnitAlbumPhoto::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('unit_id', $unit->id)
            ->whereDate('taken_on', now()->toDateString())
            ->with(['worker', 'unit.tenant'])
            ->orderBy('id')
            ->get();

        $disk = Storage::disk(UnitAlbumPhoto::DISK);
        $previewUrls = [];
        foreach ($photos->where('worker_id', $worker->id) as $photo) {
            $path = (string) $photo->path;
            try {
                $previewUrls[(int) $photo->id] = $disk->temporaryUrl($path, now()->addMinutes(10));
            } catch (\RuntimeException) {
                if (! $disk->exists($path)) {
                    continue;
                }
                $mime = $disk->mimeType($path) ?: 'image/jpeg';
                $previewUrls[(int) $photo->id] = 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
            }
        }

        return [
            'photos' => $photos,
            'previewUrls' => $previewUrls,
            'count' => $photos->count(),
            'max' => UploadUnitAlbumPhotoAction::maxPerDay(),
        ];
    }
}
