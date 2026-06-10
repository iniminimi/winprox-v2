<?php

use App\Support\Qr\QrStickerNumber;

it('formats canonical sticker numbers with Winprox prefix for display', function () {
    expect(QrStickerNumber::display('2606-12345'))->toBe('Winprox-2606-12345')
        ->and(QrStickerNumber::display('QR-2026-12345'))->toBe('Winprox-QR-2026-12345')
        ->and(QrStickerNumber::display('Winprox-2606-12345'))->toBe('Winprox-2606-12345');
});
