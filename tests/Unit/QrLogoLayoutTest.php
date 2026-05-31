<?php

declare(strict_types=1);

use App\Support\Qr\QrLogoLayout;

it('gebruikt het maximum veilige logoformaat voor H-foutcorrectie', function (): void {
    expect(QrLogoLayout::DISPLAY_BOX_RATIO)->toBe(0.30)
        ->and(QrLogoLayout::displayBoxPercent())->toBe(30)
        ->and(QrLogoLayout::STICKER_BOX_RATIO)->toBeGreaterThan(0.25)
        ->and(QrLogoLayout::BOX_INNER_PADDING_RATIO)->toBeLessThan(0.05);
});
