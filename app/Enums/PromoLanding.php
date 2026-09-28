<?php

declare(strict_types=1);

namespace App\Enums;

enum PromoLanding: string
{
    case Hospitality = 'hospitality';
    case Industry = 'industry';
    case Healthcare = 'healthcare';
    case Government = 'government';
    case RealEstate = 'realestate';
    case WorkOnLocation = 'work-on-location';
    case Checkmate = 'checkmate';

    public static function default(): self
    {
        return self::Government;
    }

    public function routeName(): string
    {
        return $this->value;
    }

    public function visitPage(): PromoVisitPage
    {
        return match ($this) {
            self::Hospitality => PromoVisitPage::Hospitality,
            self::Industry => PromoVisitPage::Industry,
            self::Healthcare => PromoVisitPage::Healthcare,
            self::Government => PromoVisitPage::Government,
            self::RealEstate => PromoVisitPage::RealEstate,
            self::WorkOnLocation => PromoVisitPage::WorkOnLocation,
            self::Checkmate => PromoVisitPage::Checkmate,
        };
    }

    public function labelKey(): string
    {
        return 'landings.'.$this->value.'.nav_label';
    }
}
