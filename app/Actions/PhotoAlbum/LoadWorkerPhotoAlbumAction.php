<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\ClockPoint;
use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\Worker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Fotoalbum op het Clock Point-portaal: units van de locatie van het
 * klokpunt met album aan (thuisgroep eerst gekozen), en de foto's van vandaag.
 */
final class LoadWorkerPhotoAlbumAction
{
    /**
     * @return Collection<int, Unit>
     */
    public function units(Worker $worker, ?ClockPoint $clockPoint): Collection
    {
        $worker->loadMissing('tenant');
        if ($worker->tenant?->checkmateMode()) {
            return collect();
        }

        return Unit::query()
            ->where('tenant_id', $worker->tenant_id)
            ->where('photo_album_enabled', true)
            ->where('is_active', true)
            ->when($clockPoint?->location_id !== null, fn ($q) => $q->where('location_id', $clockPoint->location_id))
            ->with('translations')
            ->orderBy('name')
            ->get()
            ->filter(fn (Unit $unit): bool => $worker->canClockAt((int) $unit->location_id))
            ->values();
    }

    /**
     * @return array{units: Collection<int, Unit>, unit: ?Unit, photos: Collection<int, UnitAlbumPhoto>, previewUrls: array<int, string>, count: int, max: int}
     */
    public function handle(Worker $worker, ?ClockPoint $clockPoint, ?int $unitId): array
    {
        $units = $this->units($worker, $clockPoint);
        $unit = ($unitId !== null ? $units->firstWhere('id', $unitId) : null)
            ?? $units->firstWhere('id', $worker->default_unit_id)
            ?? $units->first();

        $photos = collect();
        if ($unit !== null) {
            $photos = UnitAlbumPhoto::query()
                ->where('tenant_id', $worker->tenant_id)
                ->where('unit_id', $unit->id)
                ->whereDate('taken_on', now()->toDateString())
                ->orderBy('id')
                ->get();
        }

        $disk = Storage::disk(UnitAlbumPhoto::DISK);
        $previewUrls = [];
        foreach ($photos->where('worker_id', $worker->id) as $photo) {
            $previewUrls[(int) $photo->id] = $disk->temporaryUrl((string) $photo->path, now()->addMinutes(10));
        }

        return [
            'units' => $units,
            'unit' => $unit,
            'photos' => $photos,
            'previewUrls' => $previewUrls,
            'count' => $photos->count(),
            'max' => UploadUnitAlbumPhotoAction::maxPerDay(),
        ];
    }
}
