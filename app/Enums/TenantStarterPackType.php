<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStarterPackType: string
{
    case Hotel = 'hotel';
    case Hospital = 'hospital';
    case Industry = 'industry';
    case Municipality = 'municipality';

    public function labelKey(): string
    {
        return 'starter_pack.types.'.$this->value;
    }
}
