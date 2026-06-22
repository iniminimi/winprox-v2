<?php

declare(strict_types=1);

use App\Support\Qr\BrandedQrStickerLogoRaster;

it('keeps transparent pixels when compositing on imagick via gd raster', function () {
    if (! extension_loaded('gd') || ! extension_loaded('imagick')) {
        test()->markTestSkipped('PHP gd and imagick extensions required.');
    }

    $logoPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wp-logo-alpha-'.uniqid('', true).'.png';
    $logo = imagecreatetruecolor(80, 40);
    imagealphablending($logo, false);
    imagesavealpha($logo, true);
    $transparent = imagecolorallocatealpha($logo, 0, 0, 0, 127);
    imagefill($logo, 0, 0, $transparent);
    imagealphablending($logo, true);
    $accent = imagecolorallocate($logo, 0, 180, 200);
    imagefilledellipse($logo, 40, 20, 24, 24, $accent);
    imagepng($logo, $logoPath);
    imagedestroy($logo);

    try {
        $canvas = new Imagick;
        $canvas->newImage(120, 80, new ImagickPixel('white'));
        $canvas->setImageFormat('png');

        BrandedQrStickerLogoRaster::compositeOnImagick($canvas, $logoPath, 20, 20, 80, 40);

        $pixels = $canvas->exportImagePixels(22, 22, 1, 1, 'RGB', Imagick::PIXEL_CHAR);
        $canvas->clear();

        expect($pixels)->toBeArray()
            ->and($pixels[0])->toBeGreaterThan(240)
            ->and($pixels[1])->toBeGreaterThan(240)
            ->and($pixels[2])->toBeGreaterThan(240);
    } finally {
        @unlink($logoPath);
    }
});
