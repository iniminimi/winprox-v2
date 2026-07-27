<?php

declare(strict_types=1);

namespace App\Enums;

enum IotRuleOperator: string
{
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';
    case Eq = 'eq';

    public function matches(float $value, float $threshold): bool
    {
        return match ($this) {
            self::Gt => $value > $threshold,
            self::Gte => $value >= $threshold,
            self::Lt => $value < $threshold,
            self::Lte => $value <= $threshold,
            self::Eq => abs($value - $threshold) < 0.0001,
        };
    }

    public function labelKey(): string
    {
        return 'iot.operators.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
