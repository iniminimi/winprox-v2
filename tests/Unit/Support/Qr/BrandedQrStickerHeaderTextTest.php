<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Qr\BrandedQrStickerHeaderText;

it('prefers tenant Avery 62x89 header text over portal fallback', function () {
    $tenant = Tenant::factory()->make([
        'qr_sticker_avery_62x89_header_text' => 'Meld hier',
    ]);

    expect(BrandedQrStickerHeaderText::resolve($tenant, "Hal C · Lift 9"))->toBe('Meld hier');
});

it('uses portal fallback when tenant Avery 62x89 header text is empty', function () {
    $tenant = Tenant::factory()->make([
        'qr_sticker_avery_62x89_header_text' => null,
    ]);

    expect(BrandedQrStickerHeaderText::resolve($tenant, "Hal C · Lift 9"))->toBe('Hal C · Lift 9');
});

it('returns null when tenant and fallback header text are empty', function () {
    expect(BrandedQrStickerHeaderText::resolve(null, null))->toBeNull()
        ->and(BrandedQrStickerHeaderText::resolve(Tenant::factory()->make(), '   '))->toBeNull();
});
