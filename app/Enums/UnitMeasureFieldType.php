<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitMeasureFieldType: string
{
    case Numeric = 'numeric';
    case Boolean = 'boolean';
    case String = 'string';
    case Choice = 'choice';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function valueColumn(): string
    {
        return match ($this) {
            self::Numeric => 'value_numeric',
            self::Boolean => 'value_boolean',
            self::String, self::Choice => 'value_string',
        };
    }

    public function usesOptionList(): bool
    {
        return $this === self::Choice;
    }

    public function usesUnitOfMeasure(): bool
    {
        return $this === self::Numeric;
    }
}
