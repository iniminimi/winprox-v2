<?php

namespace App\Actions\Retention;

use App\Models\TenantTimeArchive;
use App\Support\Time\TimeArchiveWindow;
use Carbon\CarbonInterface;

/**
 * Kaarten op het beginscherm: fotobestand zolang het er staat,
 * jaarbestand alleen in februari van het jaar waarin het gemaakt hoort te worden.
 */
final class ListTimeArchiveNoticesAction
{
    /**
     * @return array{year: ?int, photo_until: ?CarbonInterface}
     */
    public function handle(int $tenantId, ?CarbonInterface $now = null): array
    {
        $now = TimeArchiveWindow::brussels($now);
        $photo = TenantTimeArchive::query()
            ->where('tenant_id', $tenantId)
            ->where('kind', TenantTimeArchive::KIND_PHOTOS)
            ->where('available_until', '>', $now)
            ->orderBy('available_until')
            ->first();

        $year = null;
        if ((int) $now->month === 2) {
            $key = (string) ($now->year - 2);
            $exists = TenantTimeArchive::query()
                ->where('tenant_id', $tenantId)
                ->where('kind', TenantTimeArchive::KIND_YEAR)
                ->where('period_key', $key)
                ->where('available_until', '>', $now)
                ->exists();
            if ($exists) {
                $year = (int) $key;
            }
        }

        return [
            'year' => $year,
            'photo_until' => $photo?->available_until,
        ];
    }
}
