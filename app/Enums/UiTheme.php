<?php

declare(strict_types=1);

namespace App\Enums;

enum UiTheme: string
{
    case Modern = 'modern';
    case Simple = 'simple';
    case Dark = 'dark';

    public static function default(): self
    {
        return self::Modern;
    }

    public static function tryFromString(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /** @return list<self> */
    public static function choices(): array
    {
        return [self::Modern, self::Simple, self::Dark];
    }
}
