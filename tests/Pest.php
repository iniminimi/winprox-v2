<?php

use App\Models\Category;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '0');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * Zorg dat issues/taken/kalender/locaties niet achter onboarding-banners blijven hangen.
 */
function seedTenantPastOnboarding(Tenant|int $tenant): void
{
    $tenantId = $tenant instanceof Tenant ? (int) $tenant->id : $tenant;

    if (InternalTeam::query()->where('tenant_id', $tenantId)->doesntExist()) {
        InternalTeam::factory()->create(['tenant_id' => $tenantId]);
    }

    if (Category::query()->where('tenant_id', $tenantId)->doesntExist()) {
        Category::factory()->create(['tenant_id' => $tenantId]);
    }

    $location = Location::query()->where('tenant_id', $tenantId)->first()
        ?? Location::factory()->create(['tenant_id' => $tenantId]);

    if (Unit::query()->where('tenant_id', $tenantId)->doesntExist()) {
        Unit::factory()->create([
            'tenant_id' => $tenantId,
            'location_id' => $location->id,
        ]);
    }

    if (ClockPoint::query()->where('tenant_id', $tenantId)->doesntExist()) {
        ClockPoint::factory()->create(['tenant_id' => $tenantId]);
    }
}

/**
 * @return list<string>
 */
function expectedTargetLocales(string $sourceLocale = 'nl'): array
{
    return array_values(array_filter(
        config('locales.supported', []),
        fn (string $locale) => $locale !== $sourceLocale,
    ));
}
