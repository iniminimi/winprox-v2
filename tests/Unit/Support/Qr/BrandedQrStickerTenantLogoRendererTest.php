<?php

declare(strict_types=1);

use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\Avery62x89StickerArtworkLayout;
use App\Support\Qr\BrandedQrStickerSurfaceFrame;
use App\Support\Qr\BrandedQrStickerTenantLogoRenderer;

it('renders transparent tenant logo corners as white on gd canvas', function () {
    if (! extension_loaded('gd')) {
        test()->markTestSkipped('PHP gd extension required.');
    }

    $logo = imagecreatetruecolor(40, 40);
    imagealphablending($logo, false);
    imagesavealpha($logo, true);
    $transparent = imagecolorallocatealpha($logo, 0, 0, 0, 127);
    imagefill($logo, 0, 0, $transparent);
    $red = imagecolorallocate($logo, 200, 0, 0);
    imagefilledellipse($logo, 20, 20, 12, 12, $red);

    $logoPath = tempnam(sys_get_temp_dir(), 'wp-logo-').'.png';
    imagepng($logo, $logoPath);
    imagedestroy($logo);

    $canvas = imagecreatetruecolor(300, 300);
    $black = imagecolorallocate($canvas, 0, 0, 0);
    imagefill($canvas, 0, 0, $black);

    BrandedQrStickerTenantLogoRenderer::drawOnGd(
        $canvas,
        $logoPath,
        QrStickerTenantLogoPlacement::TopLeft,
    );

    $inset = BrandedQrStickerSurfaceFrame::contentInsetPx();
    $sampleX = Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_LEFT_PX + $inset + 2;
    $sampleY = Avery62x89StickerArtworkLayout::TENANT_LOGO_PADDING_TOP_PX + $inset + 2;
    $color = imagecolorat($canvas, $sampleX, $sampleY);
    $redChannel = ($color >> 16) & 0xFF;
    $greenChannel = ($color >> 8) & 0xFF;
    $blueChannel = $color & 0xFF;

    expect($redChannel)->toBeGreaterThan(240)
        ->and($greenChannel)->toBeGreaterThan(240)
        ->and($blueChannel)->toBeGreaterThan(240);

    @unlink($logoPath);
    imagedestroy($canvas);
});
