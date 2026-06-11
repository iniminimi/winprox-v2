<?php

declare(strict_types=1);

use App\Support\Qr\Avery62x89StickerArtworkLayout;
use App\Support\Qr\BrandedQrStickerCompositor;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerBackground;

it('composites branded sticker at full artwork canvas size', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $compositor = new BrandedQrStickerCompositor;
    $bytes = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
    );

    $info = getimagesizefromstring($bytes);
    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX)
        ->and($info[1])->toBe(Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX);
});

it('renders branded sticker header and footer text', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $compositor = new BrandedQrStickerCompositor;
    $plain = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
    );
    $labeled = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
        'Hal C · Lift 9',
        'Winprox-2606-12345',
    );

    expect($labeled)->not->toBe($plain);
});

it('ignores blank branded sticker header and footer text', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $compositor = new BrandedQrStickerCompositor;
    $plain = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
    );
    $blank = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
        '   ',
        '   ',
    );

    expect($blank)->toBe($plain);
});
