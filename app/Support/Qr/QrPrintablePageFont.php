<?php

declare(strict_types=1);

namespace App\Support\Qr;

use RuntimeException;

/** Inter for printable A6/A5/A4 QR page labels. */
final class QrPrintablePageFont
{
    public const REGULAR_RELATIVE = 'fonts/Inter-Regular.ttf';

    public const SEMIBOLD_RELATIVE = 'fonts/Inter-SemiBold.ttf';

    public static function regularAbsolutePath(): string
    {
        return self::absolutePath(self::REGULAR_RELATIVE);
    }

    public static function semiboldAbsolutePath(): string
    {
        return self::absolutePath(self::SEMIBOLD_RELATIVE);
    }

    private static function absolutePath(string $relative): string
    {
        $path = public_path($relative);
        if (! is_file($path)) {
            throw new RuntimeException('Printable QR page font is missing at '.$relative.'.');
        }

        return $path;
    }
}
