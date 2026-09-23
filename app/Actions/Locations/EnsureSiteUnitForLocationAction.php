<?php

declare(strict_types=1);

namespace App\Actions\Locations;

use App\Enums\UnitTranslationStatus;
use App\Models\Location;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Support\Locations\SiteUnitCatalog;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Zorgt dat een locatie zonder units één "Hele locatie"-unit heeft
 * (QR / unit checks / inspectiestops zonder ruimtes op te delen).
 */
class EnsureSiteUnitForLocationAction
{
    public function __construct(
        private CreateUnitAction $createUnit,
    ) {}

    public function handle(Location $location, int $tenantId, ?int $actorUserId = null): ?Unit
    {
        if ((int) $location->tenant_id !== $tenantId) {
            return null;
        }

        if ($location->units()->exists()) {
            return null;
        }

        $name = SiteUnitCatalog::SOURCE_NAME;
        if ($location->units()->where('name', $name)->exists()) {
            $name = $name.' (1)';
        }

        $payload = [
            'name' => $name,
            'original_language' => SiteUnitCatalog::SOURCE_LOCALE,
            'public_reports_enabled' => true,
            'allow_unit_checks' => true,
            'allow_reservations' => false,
            'allow_unit_measurements' => false,
            'require_reporter_contact' => false,
            'require_reporter_email_verification' => false,
        ];

        if (Schema::hasColumn('units', 'is_site_unit')) {
            $payload['is_site_unit'] = true;
        }

        $unit = $this->createUnit->handle($location, $payload, $tenantId, $actorUserId);

        if (Schema::hasColumn('units', 'is_site_unit') && ! $unit->is_site_unit) {
            $unit->forceFill(['is_site_unit' => true])->save();
            $unit = $unit->fresh();
        }

        $this->fillTranslations($unit);

        return $unit;
    }

    private function fillTranslations(Unit $unit): void
    {
        $sourceLocale = LocaleSupport::normalize($unit->original_language) ?? SiteUnitCatalog::SOURCE_LOCALE;

        foreach (SiteUnitCatalog::namesByLocale() as $locale => $translatedName) {
            if ($locale === $sourceLocale) {
                continue;
            }

            UnitTranslation::query()->updateOrCreate(
                [
                    'unit_id' => $unit->id,
                    'locale' => $locale,
                ],
                [
                    'name' => $translatedName,
                    'description' => null,
                    'status' => UnitTranslationStatus::Completed,
                ],
            );
        }
    }
}
