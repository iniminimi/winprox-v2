<?php

namespace App\Enums;

enum PresenceComplianceScope: string
{
    case CiaoCleaning = 'ciao_cleaning';
    case CiaoConstruction = 'ciao_construction';

    public function isAvailable(): bool
    {
        return match ($this) {
            self::CiaoCleaning => true,
            self::CiaoConstruction => (bool) config('rsz.construction_scope_enabled', false),
        };
    }
}
