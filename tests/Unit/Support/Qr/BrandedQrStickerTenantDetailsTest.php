<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Qr\BrandedQrStickerTenantDetails;

it('builds tenant sticker address lines from organisation fields', function () {
    $tenant = Tenant::factory()->make([
        'name' => 'Acme NV',
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    expect(BrandedQrStickerTenantDetails::lines($tenant))
        ->toBe(['Acme NV', 'Kerkstraat 12', '9000 Gent']);
});

it('omits blank tenant sticker address lines', function () {
    $tenant = Tenant::factory()->make([
        'name' => 'Acme NV',
        'street' => '',
        'house_number' => '',
        'postal_code' => '',
        'city' => '',
    ]);

    expect(BrandedQrStickerTenantDetails::lines($tenant))->toBe(['Acme NV'])
        ->and(BrandedQrStickerTenantDetails::lines(null))->toBe([]);
});
