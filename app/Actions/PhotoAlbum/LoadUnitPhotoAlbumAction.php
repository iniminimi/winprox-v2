<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumRecipient;
use App\Models\UnitAlbumSend;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Leest het fotoalbum voor beheer: units met album, dagen binnen de
 * bewaartermijn, foto's van de gekozen dag, ontvangers en verzendingen.
 */
final class LoadUnitPhotoAlbumAction
{
    /**
     * @return array{
     *     units: Collection<int, Unit>,
     *     unit: ?Unit,
     *     days: list<string>,
     *     day: string,
     *     photos: Collection<int, UnitAlbumPhoto>,
     *     recipients: Collection<int, UnitAlbumRecipient>,
     *     sends: Collection<int, UnitAlbumSend>
     * }
     */
    public function handle(int $tenantId, ?int $unitId, ?string $day): array
    {
        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('photo_album_enabled', true)
            ->where('is_active', true)
            ->with(['location', 'translations'])
            ->orderBy('name')
            ->get();

        $unit = $unitId !== null ? $units->firstWhere('id', $unitId) : null;
        $unit ??= $units->first();

        $today = CarbonImmutable::today();
        $retention = max(1, (int) config('data_retention.unit_album_photos_days', 7));
        $days = [];
        for ($i = 0; $i < $retention; $i++) {
            $days[] = $today->subDays($i)->toDateString();
        }
        $day = in_array($day, $days, true) ? (string) $day : $days[0];

        $photos = collect();
        $recipients = collect();
        $sends = collect();

        if ($unit !== null) {
            $photos = UnitAlbumPhoto::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unit->id)
                ->whereDate('taken_on', $day)
                ->with('worker')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            $recipients = UnitAlbumRecipient::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unit->id)
                ->orderByDesc('last_sent_at')
                ->orderBy('email')
                ->get();

            $sends = UnitAlbumSend::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unit->id)
                ->with('user')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return [
            'units' => $units,
            'unit' => $unit,
            'days' => $days,
            'day' => $day,
            'photos' => $photos,
            'recipients' => $recipients,
            'sends' => $sends,
        ];
    }
}
