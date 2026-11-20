<?php

declare(strict_types=1);

use App\Support\Qr\QrPrintablePageFont;
use App\Support\Qr\QrTtfWrappedText;

it('wraps a long printable headline onto multiple lines within the max width', function () {
    $font = QrPrintablePageFont::semiboldAbsolutePath();
    $maxWidth = 420;
    $text = 'Scan deze QR-code en maak een melding';

    $fit = QrTtfWrappedText::fit($text, $font, 42.0, 26.0, $maxWidth, 4);

    expect($fit['lines'])->not->toBeEmpty()
        ->and(count($fit['lines']))->toBeGreaterThan(1)
        ->and(count($fit['lines']))->toBeLessThanOrEqual(4);

    foreach ($fit['lines'] as $line) {
        expect(QrTtfWrappedText::textWidth($font, $fit['font_size'], $line))->toBeLessThanOrEqual($maxWidth);
    }
});

it('keeps a short label on one line', function () {
    $font = QrPrintablePageFont::regularAbsolutePath();
    $fit = QrTtfWrappedText::fit('Duinhummeltjes', $font, 28.0, 16.0, 500, 3);

    expect($fit['lines'])->toBe(['Duinhummeltjes']);
});

it('honours explicit line breaks up to the max line count', function () {
    $font = QrPrintablePageFont::semiboldAbsolutePath();
    $fit = QrTtfWrappedText::fit("Regel één\nRegel twee\nRegel drie", $font, 30.0, 18.0, 800, 2);

    expect($fit['lines'])->toHaveCount(2)
        ->and($fit['lines'][0])->toBe('Regel één')
        ->and($fit['lines'][1])->toBe('Regel twee');
});
