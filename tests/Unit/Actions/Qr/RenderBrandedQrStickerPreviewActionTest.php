<?php

declare(strict_types=1);

use App\Actions\Qr\RenderBrandedQrStickerPreviewAction;
use App\Data\Qr\BrandedQrStickerPreviewData;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Models\Tenant;
use App\Support\Qr\QrCodePngWriter;

it('renders a branded Avery 62x89 sticker preview as a data url', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $tenant = Tenant::factory()->create([
        'name' => 'Acme NV',
        'street' => 'Kerkstraat',
        'house_number' => '12',
        'postal_code' => '9000',
        'city' => 'Gent',
    ]);

    $dataUrl = app(RenderBrandedQrStickerPreviewAction::class)->handle(
        $tenant,
        new BrandedQrStickerPreviewData(
            headerText: 'Scan deze QR-code',
            tenantLogoPlacement: QrStickerTenantLogoPlacement::BottomRight,
            tenantAddressPlacement: QrStickerTenantLogoPlacement::BottomLeft,
        ),
    );

    expect($dataUrl)->toStartWith('data:image/png;base64,');
});
