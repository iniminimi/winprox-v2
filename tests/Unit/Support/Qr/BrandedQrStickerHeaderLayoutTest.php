<?php

declare(strict_types=1);

use App\Support\Qr\Avery62x89StickerArtworkLayout;
use App\Support\Qr\BrandedQrStickerCompositor;
use App\Support\Qr\BrandedQrStickerFont;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerBackground;

it('fits the default Avery 62x89 portal header text in three lines at the sticker width', function () {
    $font = BrandedQrStickerFont::headerBoldAbsolutePath();
    $text = 'Scan deze QR-code en kom terecht in ons Portaal. Je kan er meldingen maken, documenten en mededelingen bekijken.';
    $maxWidth = Avery62x89StickerArtworkLayout::headerMaxWidthPx();

    expect(mb_strlen($text))->toBeLessThanOrEqual(Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS);

    for ($fontSize = Avery62x89StickerArtworkLayout::HEADER_MAX_FONT_SIZE_PX;
        $fontSize >= Avery62x89StickerArtworkLayout::HEADER_MIN_FONT_SIZE_PX;
        $fontSize--) {
        $lines = wrapHeaderWords($text, $font, $fontSize, $maxWidth);
        if (count($lines) <= Avery62x89StickerArtworkLayout::HEADER_MAX_LINES
            && linesFitWidth($lines, $font, $fontSize, $maxWidth)) {
            expect($lines)->toHaveCount(3)
                ->and($lines[2])->toContain('bekijken');

            return;
        }
    }

    test()->fail('Expected default portal header text to fit in three lines.');
});

it('renders the full default portal header without changing sticker canvas size', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $text = 'Scan deze QR-code en kom terecht in ons Portaal. Je kan er meldingen maken, documenten en mededelingen bekijken.';
    $compositor = new BrandedQrStickerCompositor;
    $bytes = $compositor->compositeBytes(
        QrStickerBackground::defaultAvery62x89AbsolutePath(),
        'https://example.test/melden/demo-token',
        null,
        $text,
    );

    $info = getimagesizefromstring($bytes);
    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(Avery62x89StickerArtworkLayout::CANVAS_WIDTH_PX);
});

/**
 * @return list<string>
 */
function wrapHeaderWords(string $text, string $fontPath, int $fontSize, int $maxWidth): array
{
    $words = preg_split('/\s+/u', $text) ?: [];
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current.' '.$word;
        if (headerTextWidth($fontPath, $fontSize, $candidate) <= $maxWidth) {
            $current = $candidate;

            continue;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        $current = $word;
    }

    if ($current !== '') {
        $lines[] = $current;
    }

    return $lines;
}

/**
 * @param  list<string>  $lines
 */
function linesFitWidth(array $lines, string $fontPath, int $fontSize, int $maxWidth): bool
{
    foreach ($lines as $line) {
        if (headerTextWidth($fontPath, $fontSize, $line) > $maxWidth) {
            return false;
        }
    }

    return true;
}

function headerTextWidth(string $fontPath, int $fontSize, string $text): int
{
    $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
    if (! is_array($bbox)) {
        throw new RuntimeException('Unable to measure header text width.');
    }

    return (int) abs($bbox[2] - $bbox[0]);
}
