<?php

declare(strict_types=1);

use App\Enums\QrStickerTenantLogoPlacement;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\BrandedQrStickerLayoutConfig;

it('uses Avery 62x89 layout defaults when layout_config is empty', function () {
    $layout = BrandedQrStickerLayoutConfig::fromSetting(null);

    expect($layout->tenantLogoPlacement())->toBe(QrStickerTenantLogoPlacement::BottomRight)
        ->and($layout->showTenantAddress())->toBeTrue()
        ->and($layout->usesDefaults())->toBeTrue();
});

it('reads tenant logo and address placement from layout_config', function () {
    $layout = BrandedQrStickerLayoutConfig::fromSetting(TenantQrStickerSheetSetting::factory()->make([
        'layout_config' => ['tenant_logo' => 'top_right', 'tenant_address' => 'none'],
    ]));

    expect($layout->tenantLogoPlacement())->toBe(QrStickerTenantLogoPlacement::TopRight)
        ->and($layout->tenantAddressPlacement())->toBe(QrStickerTenantLogoPlacement::None)
        ->and($layout->showTenantAddress())->toBeFalse()
        ->and($layout->showTenantLogoOnSticker())->toBeTrue();
});

it('maps legacy tenant_address boolean to placement', function () {
    $layout = BrandedQrStickerLayoutConfig::fromSetting(TenantQrStickerSheetSetting::factory()->make([
        'layout_config' => ['tenant_address' => false],
    ]));

    expect($layout->tenantAddressPlacement())->toBe(QrStickerTenantLogoPlacement::None);
});

it('maps legacy corner_tenant_logo false to no sticker logo', function () {
    $layout = BrandedQrStickerLayoutConfig::fromSetting(TenantQrStickerSheetSetting::factory()->make([
        'layout_config' => ['corner_tenant_logo' => false],
    ]));

    expect($layout->tenantLogoPlacement())->toBe(QrStickerTenantLogoPlacement::None);
});
