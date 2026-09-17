<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStarterPackType: string
{
    case OwnSites = 'own_sites';
    case OnSite = 'on_site';
    case Fleet = 'fleet';
    case Hotel = 'hotel';
    case Hospital = 'hospital';
    case Industry = 'industry';
    case Municipality = 'municipality';
    case RealEstate = 'realestate';

    /**
     * @return list<self>
     */
    public static function onboardingChoices(): array
    {
        return [self::OwnSites, self::OnSite, self::Fleet];
    }

    public function labelKey(): string
    {
        return 'starter_pack.types.'.$this->value;
    }

    public function hintKey(): string
    {
        return 'starter_pack.hints.'.$this->value;
    }

    public function asksCompanySize(): bool
    {
        return $this === self::OnSite;
    }
}
