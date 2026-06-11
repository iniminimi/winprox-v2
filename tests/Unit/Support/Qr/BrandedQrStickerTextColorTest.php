<?php

declare(strict_types=1);

use App\Support\Qr\BrandedQrStickerCompositor;
use App\Support\Qr\BrandedQrStickerTextColor;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerBackground;

it('uses dark label color on the light header band of the default artwork', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $background = @imagecreatefrompng(QrStickerBackground::defaultAvery62x89AbsolutePath());
    expect($background)->not->toBeFalse();

    [$r, $g, $b] = BrandedQrStickerTextColor::rgbForGdRegion(
        $background,
        200,
        BrandedQrStickerTextColor::headerSampleCenterY(),
        400,
        120,
    );

    imagedestroy($background);

    expect($r)->toBeLessThan(50)
        ->and($g)->toBeLessThan(50)
        ->and($b)->toBeLessThan(50);
});

it('draws visible header text on the current light artwork background', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $compositor = new BrandedQrStickerCompositor;
    $bytes = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
        'Meld hier',
        'Winprox-2606-12345',
    );

    $image = imagecreatefromstring($bytes);
    expect($image)->not->toBeFalse();

    $hasDarkInk = false;
    for ($y = 70; $y <= 150; $y += 4) {
        for ($x = 40; $x <= 420; $x += 4) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if ($r < 80 && $g < 80 && $b < 80) {
                $hasDarkInk = true;
                break 2;
            }
        }
    }

    imagedestroy($image);

    expect($hasDarkInk)->toBeTrue();
});

it('draws tenant details in fixed dark ink on the light bottom band', function () {
    if (! QrCodePngWriter::canGenerate()) {
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
        ['Gemeente Knokke-Heist', 'Albertplein 1', '8300 Knokke-Heist'],
    );

    $image = imagecreatefromstring($bytes);
    expect($image)->not->toBeFalse();

    $hasDarkInk = false;
    for ($y = 930; $y <= 990; $y += 3) {
        for ($x = 36; $x <= 320; $x += 3) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            if ($r < 80 && $g < 80 && $b < 80) {
                $hasDarkInk = true;
                break 2;
            }
        }
    }

    imagedestroy($image);

    expect($hasDarkInk)->toBeTrue();
});
