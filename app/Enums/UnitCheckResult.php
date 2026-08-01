<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitCheckResult: string
{
    case Ok = 'ok';
    case NotOk = 'not_ok';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Maps onto shared `.wp-pill` variants.
     */
    public function pillVariant(): string
    {
        return match ($this) {
            self::Ok => 'done',
            self::NotOk => 'closed',
        };
    }
}
