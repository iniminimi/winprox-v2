<?php

declare(strict_types=1);

namespace App\Enums;

enum QrStickerCenterLogoMode: string
{
    case Tenant = 'tenant';

    case Winprox = 'winprox';

    case None = 'none';

    public static function default(): self
    {
        return self::Tenant;
    }

    public static function tryFromString(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /** @return list<self> */
    public static function choices(): array
    {
        return [self::Tenant, self::Winprox, self::None];
    }
}
