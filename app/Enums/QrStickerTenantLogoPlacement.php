<?php

declare(strict_types=1);

namespace App\Enums;

enum QrStickerTenantLogoPlacement: string
{
    case None = 'none';

    case BottomRight = 'bottom_right';

    case BottomLeft = 'bottom_left';

    case TopRight = 'top_right';

    case TopLeft = 'top_left';

    public static function default(): self
    {
        return self::BottomRight;
    }

    public static function tryFromString(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /** @return list<self> */
    public static function choices(): array
    {
        return [
            self::BottomRight,
            self::TopRight,
            self::BottomLeft,
            self::TopLeft,
            self::None,
        ];
    }
}
