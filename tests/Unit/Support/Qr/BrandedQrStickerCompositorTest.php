<?php

declare(strict_types=1);

use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\Avery62x89StickerArtworkLayout;
use App\Support\Qr\BrandedQrStickerCompositor;
use App\Support\Qr\QrCenterLogo;
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

it('renders tenant details and corner logo in the bottom band', function () {
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
        null,
        null,
        null,
        ['Acme NV', 'Kerkstraat 12', '9000 Gent'],
        QrCenterLogo::winproxAbsolutePath(),
    );

    expect($labeled)->not->toBe($plain);
});

it('uses one full-width white bottom band for tenant address and bottom logo', function () {
    if (! extension_loaded('gd') || ! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $compositor = new BrandedQrStickerCompositor;
    $bytes = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
        null,
        null,
        null,
        ['Westtoer', 'Koning Albert I-laan 120', '8200 Brugge'],
        QrCenterLogo::winproxAbsolutePath(),
        QrStickerTenantLogoPlacement::BottomRight,
    );

    $image = imagecreatefromstring($bytes);
    expect($image)->not->toBeFalse();

    $centerX = (int) round(Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX / 2);
    $sampleY = Avery62x89StickerArtworkLayout::CANVAS_HEIGHT_PX - 90;
    $color = imagecolorat($image, $centerX, $sampleY);
    imagedestroy($image);

    $red = ($color >> 16) & 0xFF;
    $green = ($color >> 8) & 0xFF;
    $blue = $color & 0xFF;

    expect($red)->toBeGreaterThan(240)
        ->and($green)->toBeGreaterThan(240)
        ->and($blue)->toBeGreaterThan(240);
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
