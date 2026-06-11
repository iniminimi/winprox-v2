<?php

declare(strict_types=1);

/**
 * Zet een promo-PNG om naar Messenger-vriendelijke OG-JPEG (1200×630).
 *
 * Gebruik:
 *   php scripts/generate-og-image.php public/images/promo/mijn_foto.png
 *   php scripts/generate-og-image.php public/images/promo/mijn_foto.png og_3
 *
 * Output: public/images/promo/og_{naam}.jpg
 *   og_1.jpg = welcome/promo/site; og_2.jpg = QR-portaal (/melden, /team, …).
 */

$sourcePath = $argv[1] ?? null;

if ($sourcePath === null || ! is_string($sourcePath)) {
    fwrite(STDERR, "Gebruik: php scripts/generate-og-image.php <bron.png> [og_naam]\n");
    exit(1);
}
$ogBasename = $argv[2] ?? null;

if (! is_file($sourcePath)) {
    fwrite(STDERR, "Bronbestand niet gevonden: {$sourcePath}\n");
    exit(1);
}

if ($ogBasename === null) {
    $ogBasename = 'og_'.pathinfo($sourcePath, PATHINFO_FILENAME);
}

$outputPath = __DIR__.'/../public/images/promo/'.$ogBasename.'.jpg';

$src = imagecreatefromstring((string) file_get_contents($sourcePath));
if ($src === false) {
    fwrite(STDERR, "Kon bronafbeelding niet laden.\n");
    exit(1);
}

$dstW = 1200;
$dstH = 630;
$srcW = imagesx($src);
$srcH = imagesy($src);
$cropY = (int) max(0, ($srcH - $dstH) / 2);

$dst = imagecreatetruecolor($dstW, $dstH);
imagecopyresampled($dst, $src, 0, 0, 0, $cropY, $dstW, $dstH, min($dstW, $srcW), min($dstH, $srcH - $cropY));

if (! imagejpeg($dst, $outputPath, 82)) {
    fwrite(STDERR, "JPEG schrijven mislukt.\n");
    exit(1);
}

imagedestroy($src);
imagedestroy($dst);

$size = filesize($outputPath);
[$width, $height] = getimagesize($outputPath);

echo "Wrote {$outputPath} ({$width}x{$height}, {$size} bytes)\n";
