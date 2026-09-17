<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStarterPackType: string
{
    case OnSite = 'on_site';
    case Hotel = 'hotel';
    case Hospital = 'hospital';
    case Industry = 'industry';
    case Municipality = 'municipality';
    case RealEstate = 'realestate';
    case Fleet = 'fleet';

    public function labelKey(): string
    {
        return 'starter_pack.types.'.$this->value;
    }

    public function asksCompanySize(): bool
    {
        return match ($this) {
            self::OnSite, self::Hotel, self::RealEstate => true,
            default => false,
        };
    }
}
