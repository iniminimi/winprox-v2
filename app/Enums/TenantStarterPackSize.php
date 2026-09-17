<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStarterPackSize: string
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function labelKey(): string
    {
        return 'dashboard.starter_pack.sizes.'.$this->value;
    }
}
