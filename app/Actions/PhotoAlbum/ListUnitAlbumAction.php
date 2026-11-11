<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumRecipient;
use App\Models\UnitAlbumSend;
use Illuminate\Support\Collection;

/** Beheerlijst: units, foto's, ontvangers en recente verzendingen van één unit. */
final class ListUnitAlbumAction
{
    /**
     * @param  list<int>|null  $locationIds  null = alle locaties van de tenant
     * @return array{
     *     units: Collection<int, Unit>,
     *     photos: Collection<int, UnitAlbumPhoto>,
     *     recipients: Collection<int, UnitAlbumRecipient>,
     *     sends: Collection<int, UnitAlbumSend>
     * }
     */
    public function handle(int $tenantId, ?array $locationIds, ?int $unitId): array
    {
        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->orderBy('name')
            ->get(['id', 'name', 'location_id']);

        $selected = $unitId !== null && $units->contains(fn (Unit $unit): bool => (int) $unit->id === $unitId)
            ? $unitId
            : null;

        $photos = $selected === null
            ? collect()
            : UnitAlbumPhoto::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $selected)
                ->with('worker:id,first_name,last_name')
                ->orderByDesc('taken_on')
                ->orderByDesc('id')
                ->limit(60)
                ->get();

        $recipients = $selected === null
            ? collect()
            : UnitAlbumRecipient::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $selected)
                ->orderBy('email')
                ->get();

        $sends = $selected === null
            ? collect()
            : UnitAlbumSend::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $selected)
                ->orderByDesc('id')
                ->limit(8)
                ->get();

        return [
            'units' => $units,
            'photos' => $photos,
            'recipients' => $recipients,
            'sends' => $sends,
        ];
    }
}
