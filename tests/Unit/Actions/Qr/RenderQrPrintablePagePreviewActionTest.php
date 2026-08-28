<?php

declare(strict_types=1);

use App\Actions\Qr\RenderQrPrintablePagePreviewAction;
use App\Data\Qr\BrandedQrPrintablePagePreviewData;
use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\QrPrintablePageStockBackgroundCatalog;
use App\Models\Tenant;
use App\Support\Qr\QrCodePngWriter;

it('renders a printable page preview with logo and address overlays as a data url', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for preview PNG generation.');
    }

    $tenant = Tenant::factory()->create([
        'name' => 'Haven NV',
        'street' => 'Kaai',
        'house_number' => '12',
        'postal_code' => '2000',
        'city' => 'Antwerpen',
    ]);

    $dataUrl = app(RenderQrPrintablePagePreviewAction::class)->handle(
        $tenant,
        new BrandedQrPrintablePagePreviewData(
            presetKey: QrPrintablePageStockBackgroundCatalog::PRESET_PREFIX.'back_09.jpg',
            tenantLogoPlacement: QrStickerTenantLogoPlacement::BottomRight,
            tenantAddressPlacement: QrStickerTenantLogoPlacement::BottomLeft,
        ),
    );

    expect($dataUrl)->toStartWith('data:image/png;base64,');
});
