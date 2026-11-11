<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\Worker;
use Illuminate\Support\Collection;

/**
 * Units waar deze uitvoerder mag klokken, plus de foto's van vandaag
 * voor de gekozen unit.
 */
final class ListWorkerUnitAlbumAction
{
    /**
     * @return array{units: Collection<int, Unit>, photos: Collection<int, UnitAlbumPhoto>, count: int, max: int}
     */
    public function handle(Worker $worker, int $tenantId, ?int $unitId): array
    {
        $empty = [
            'units' => collect(),
            'photos' => collect(),
            'count' => 0,
            'max' => UploadUnitAlbumPhotoAction::maxPerDay(),
        ];

        if ((int) $worker->tenant_id !== $tenantId || ! $worker->is_active) {
            return $empty;
        }

        $tenant = $worker->relationLoaded('tenant') ? $worker->tenant : $worker->tenant()->first();
        if ($tenant === null || ! $tenant->photoAlbumEnabled()) {
            return $empty;
        }

        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'location_id', 'tenant_id', 'is_active'])
            ->filter(fn (Unit $unit): bool => $worker->canClockAt((int) $unit->location_id))
            ->values();

        $photos = collect();
        if ($unitId !== null && $units->contains(fn (Unit $unit): bool => (int) $unit->id === $unitId)) {
            $photos = UnitAlbumPhoto::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unitId)
                ->whereDate('taken_on', now()->toDateString())
                ->orderByDesc('id')
                ->get();
        }

        return [
            'units' => $units,
            'photos' => $photos,
            'count' => $photos->count(),
            'max' => UploadUnitAlbumPhotoAction::maxPerDay(),
        ];
    }
}
