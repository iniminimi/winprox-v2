<?php

declare(strict_types=1);

namespace App\Enums;

enum EsgIndicatorCategory: string
{
    case Energy = 'energy';
    case Water = 'water';
    case Gas = 'gas';
    case Waste = 'waste';
    case Emissions = 'emissions';
    case Compliance = 'compliance';
    case Other = 'other';

    public function labelKey(): string
    {
        return 'esg.categories.'.$this->value;
    }
}
