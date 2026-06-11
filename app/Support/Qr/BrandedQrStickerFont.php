<?php

declare(strict_types=1);

namespace App\Support\Qr;

use RuntimeException;

final class BrandedQrStickerFont
{
    public const HEADER_BOLD_RELATIVE = 'fonts/sticker-header-bold.ttf';

    public static function headerBoldAbsolutePath(): string
    {
        $path = public_path(self::HEADER_BOLD_RELATIVE);
        if (! is_file($path)) {
            throw new RuntimeException('Branded QR sticker header font is missing at '.self::HEADER_BOLD_RELATIVE.'.');
        }

        return $path;
    }
}
