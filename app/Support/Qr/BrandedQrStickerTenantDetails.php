<?php

declare(strict_types=1);

namespace App\Support\Qr;

use App\Models\Tenant;

/** Tenant name + address lines for the Avery 62×89-R bottom band. */
final class BrandedQrStickerTenantDetails
{
    /**
     * @return list<string>
     */
    public static function lines(?Tenant $tenant): array
    {
        if ($tenant === null) {
            return [];
        }

        $lines = [];

        $name = trim((string) $tenant->name);
        if ($name !== '') {
            $lines[] = $name;
        }

        $streetLine = trim(trim((string) $tenant->street).' '.trim((string) $tenant->house_number));
        if ($streetLine !== '') {
            $lines[] = $streetLine;
        }

        $cityLine = trim(trim((string) $tenant->postal_code).' '.trim((string) $tenant->city));
        if ($cityLine !== '') {
            $lines[] = $cityLine;
        }

        return array_slice($lines, 0, Avery62x89StickerArtworkLayout::TENANT_DETAILS_MAX_LINES);
    }
}
