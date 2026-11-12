<?php

declare(strict_types=1);

namespace App\Actions\PhotoAlbum;

use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UnitAlbumPhoto;
use App\Models\UnitAlbumRecipient;
use App\Models\UnitAlbumSend;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Leest het fotoalbum voor beheer: alleen units en dagen met foto's
 * binnen de bewaartermijn, plus ontvangers en verzendingen van de unit.
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
     *     sends: Collection<int, UnitAlbumSend>,
     *     retentionDays: int
     * }
     */
    public function handle(int $tenantId, ?int $unitId, ?string $day): array
    {
        $retention = max(1, (int) config('data_retention.unit_album_photos_days', 7));
        $empty = [
            'units' => collect(),
            'unit' => null,
            'days' => [],
            'day' => '',
            'photos' => collect(),
            'recipients' => collect(),
            'sends' => collect(),
            'retentionDays' => $retention,
        ];

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null || ! $tenant->photoAlbumEnabled()) {
            return $empty;
        }

        $cutoff = now()->subDays($retention);
        $unitIds = UnitAlbumPhoto::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $cutoff)
            ->distinct()
            ->pluck('unit_id');

        $units = Unit::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $unitIds)
            ->where('is_active', true)
            ->with(['location', 'translations'])
            ->orderBy('name')
            ->get();

        $unit = $unitId !== null ? $units->firstWhere('id', $unitId) : null;
        $unit ??= $units->first();
        if ($unit === null) {
            return $empty;
        }

        $days = UnitAlbumPhoto::query()
            ->where('tenant_id', $tenantId)
            ->where('unit_id', $unit->id)
            ->where('created_at', '>=', $cutoff)
            ->orderByDesc('taken_on')
            ->pluck('taken_on')
            ->map(fn ($takenOn): string => CarbonImmutable::parse($takenOn)->toDateString())
            ->unique()
            ->values()
            ->all();

        $day = in_array($day, $days, true) ? (string) $day : (string) ($days[0] ?? '');

        $photos = $day === ''
            ? collect()
            : UnitAlbumPhoto::query()
                ->where('tenant_id', $tenantId)
                ->where('unit_id', $unit->id)
                ->where('created_at', '>=', $cutoff)
                ->whereDate('taken_on', $day)
                ->with(['worker', 'unit.tenant'])
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

        return [
            'units' => $units,
            'unit' => $unit,
            'days' => $days,
            'day' => $day,
            'photos' => $photos,
            'recipients' => $recipients,
            'sends' => $sends,
            'retentionDays' => $retention,
        ];
    }
}
