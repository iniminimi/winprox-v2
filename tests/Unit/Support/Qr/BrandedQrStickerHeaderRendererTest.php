<?php

declare(strict_types=1);

use App\Support\Qr\BrandedQrStickerFont;
use App\Support\Qr\BrandedQrStickerHeaderRenderer;

it('layouts long Avery 62x89 tenant header text on three lines', function () {
    if (! extension_loaded('gd')) {
        test()->markTestSkipped('PHP gd extension required.');
    }

    $text = 'Scan deze QR-code en kom terecht in ons Portaal. Je kan er meldingen maken, documenten en mededelingen bekijken.';
    $method = new \ReflectionMethod(BrandedQrStickerHeaderRenderer::class, 'layout');
    $method->setAccessible(true);

    /** @var array{font_size: int, lines: list<string>} $layout */
    $layout = $method->invoke(null, $text, BrandedQrStickerFont::headerBoldAbsolutePath());

    expect($layout['lines'])->toHaveCount(3)
        ->and(implode(' ', $layout['lines']))->toContain('bekijken');
});
