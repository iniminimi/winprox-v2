<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\BrandedQrStickerHeaderText;
use App\Support\Qr\QrStickerSheetTemplate;

it('prefers tenant Avery 62x89 header text over portal fallback', function () {
    $settings = TenantQrStickerSheetSetting::factory()->make([
        'template' => QrStickerSheetTemplate::Avery62x89R->value,
        'header_text' => 'Meld hier',
    ]);

    expect(BrandedQrStickerHeaderText::resolve($settings, "Hal C · Lift 9"))->toBe('Meld hier');
});

it('uses portal fallback when tenant Avery 62x89 header text is empty', function () {
    expect(BrandedQrStickerHeaderText::resolve(null, "Hal C · Lift 9"))->toBe('Hal C · Lift 9');
});

it('returns null when tenant and fallback header text are empty', function () {
    expect(BrandedQrStickerHeaderText::resolve(null, null))->toBeNull()
        ->and(BrandedQrStickerHeaderText::resolve(
            TenantQrStickerSheetSetting::factory()->make(['header_text' => null]),
            '   ',
        ))->toBeNull();
});

it('shows portal unit caption below QR when tenant header text is set', function () {
    $settings = TenantQrStickerSheetSetting::factory()->make([
        'header_text' => 'Scan hier',
    ]);

    expect(BrandedQrStickerHeaderText::unitCaption($settings, "Hal C · Lift 9\nVerdieping 0"))
        ->toBe('Hal C · Lift 9')
        ->and(BrandedQrStickerHeaderText::unitCaption(null, 'Hal C · Lift 9'))->toBeNull();
});
