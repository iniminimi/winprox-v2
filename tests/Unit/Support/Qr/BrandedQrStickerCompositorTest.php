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

it('renders branded sticker header text from unit label', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $compositor = new BrandedQrStickerCompositor;
    $withoutHeader = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
    );
    $withHeader = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
        'Lift 1',
    );

    expect($withHeader)->not->toBe($withoutHeader);
});

it('ignores blank branded sticker header text', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $compositor = new BrandedQrStickerCompositor;
    $withoutHeader = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
    );
    $withBlankHeader = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
        '   ',
    );

    expect($withBlankHeader)->toBe($withoutHeader);
});
