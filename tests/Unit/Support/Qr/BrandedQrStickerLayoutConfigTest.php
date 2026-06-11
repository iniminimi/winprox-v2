<?php

declare(strict_types=1);

use App\Enums\QrStickerCenterLogoMode;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\BrandedQrStickerLayoutConfig;
use App\Support\Qr\QrCenterLogo;

it('uses Avery 62x89 layout defaults when layout_config is empty', function () {
    $layout = BrandedQrStickerLayoutConfig::fromSetting(null);

    expect($layout->centerLogoMode())->toBe(QrStickerCenterLogoMode::Tenant)
        ->and($layout->showCornerTenantLogo())->toBeTrue()
        ->and($layout->showTenantAddress())->toBeTrue()
        ->and($layout->usesDefaults())->toBeTrue();
});

it('resolves center logo paths from layout config', function () {
    $layout = BrandedQrStickerLayoutConfig::fromSetting(TenantQrStickerSheetSetting::factory()->make([
        'layout_config' => ['center_logo' => 'winprox'],
    ]));

    expect($layout->resolveCenterLogoPath(null))->toBe(QrCenterLogo::winproxAbsolutePath())
        ->and($layout->includeCenterLogo())->toBeTrue();

    $none = BrandedQrStickerLayoutConfig::fromSetting(TenantQrStickerSheetSetting::factory()->make([
        'layout_config' => ['center_logo' => 'none'],
    ]));

    expect($none->includeCenterLogo())->toBeFalse();
});
