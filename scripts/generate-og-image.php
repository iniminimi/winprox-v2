<?php

declare(strict_types=1);

$sourcePath = __DIR__.'/../public/images/promo/image_worker.png';
$outputPath = __DIR__.'/../public/images/promo/image_worker_og.jpg';

$src = imagecreatefrompng($sourcePath);
if ($src === false) {
    fwrite(STDERR, "Failed to load source PNG.\n");
    exit(1);
}

$dstW = 1200;
$dstH = 630;
$srcW = imagesx($src);
$srcH = imagesy($src);
$cropY = (int) max(0, ($srcH - $dstH) / 2);

$dst = imagecreatetruecolor($dstW, $dstH);
imagecopyresampled($dst, $src, 0, 0, 0, $cropY, $dstW, $dstH, $dstW, min($dstH, $srcH));

if (! imagejpeg($dst, $outputPath, 82)) {
    fwrite(STDERR, "Failed to write JPEG.\n");
    exit(1);
}

imagedestroy($src);
imagedestroy($dst);

$size = filesize($outputPath);
[$width, $height] = getimagesize($outputPath);

echo "Wrote {$outputPath} ({$width}x{$height}, {$size} bytes)\n";
