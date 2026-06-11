<?php

declare(strict_types=1);

use App\Support\Qr\Avery62x89StickerArtworkLayout;
use App\Support\Qr\BrandedQrStickerHeaderText;

it('limits Avery 62x89 header text to the sticker character budget', function () {
    $long = str_repeat('A', Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS + 10);

    $fitted = BrandedQrStickerHeaderText::fitForSticker($long);

    expect(mb_strlen($fitted))->toBeLessThanOrEqual(Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS)
        ->and($fitted)->toEndWith('…');
});

it('collapses repeated whitespace in Avery 62x89 header text', function () {
    expect(BrandedQrStickerHeaderText::fitForSticker("Scan deze  QR-code   en kom"))
        ->toBe('Scan deze QR-code en kom');
});
