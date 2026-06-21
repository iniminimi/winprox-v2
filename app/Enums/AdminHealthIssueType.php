<?php

namespace App\Enums;

enum AdminHealthIssueType: string
{
    case UnitMissingPhoto = 'unit_missing_photo';
    case CategoryMissingTeam = 'category_missing_team';
    case LocationMissingAddress = 'location_missing_address';

    public function labelKey(): string
    {
        return match ($this) {
            self::UnitMissingPhoto => 'health.issue.unit_photo',
            self::CategoryMissingTeam => 'health.issue.category_team',
            self::LocationMissingAddress => 'health.issue.location_address',
        };
    }
}
