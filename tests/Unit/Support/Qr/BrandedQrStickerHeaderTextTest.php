<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\BrandedQrStickerHeaderText;
use App\Support\Qr\QrStickerEntry;
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

it('uses tenant header text as printable page headline with unit caption below', function () {
    $settings = TenantQrStickerSheetSetting::factory()->make([
        'header_text' => 'Scan hier voor meldingen',
    ]);
    $entry = new QrStickerEntry(
        unitLabel: 'Winprox-2606-00001',
        reportUrl: 'https://example.test/qr/demo',
        headerFallback: "Demo locatie · Machine 12",
        stickerNumber: 'Winprox-2606-00001',
        locationUnitLabel: 'Demo locatie - Machine 12',
    );

    expect(BrandedQrStickerHeaderText::printableHeadlineAndCaption($settings, $entry))
        ->toBe([
            'headline' => 'Scan hier voor meldingen',
            'secondary' => 'Demo locatie · Machine 12',
        ]);
});

it('keeps clock point page headline when tenant header text is empty', function () {
    $entry = new QrStickerEntry(
        unitLabel: 'Winprox-2606-00001',
        reportUrl: 'https://example.test/time/demo',
        stickerNumber: 'Winprox-2606-00001',
        locationUnitLabel: 'Ingang A',
        pageHeadline: 'Scan om in te klokken',
    );

    expect(BrandedQrStickerHeaderText::printableHeadlineAndCaption(null, $entry))
        ->toBe([
            'headline' => 'Scan om in te klokken',
            'secondary' => 'Ingang A',
        ]);
});
