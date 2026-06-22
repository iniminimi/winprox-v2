<?php

namespace App\Enums;

enum AdminHealthIssueType: string
{
    case UnitMissingPhoto = 'unit_missing_photo';
    case UnitMissingGps = 'unit_missing_gps';
    case UnitPublicReportsDisabled = 'unit_public_reports_disabled';
    case InactiveDocument = 'inactive_document';
    case CategoryMissingTeam = 'category_missing_team';
    case LocationMissingAddress = 'location_missing_address';

    public function labelKey(): string
    {
        return match ($this) {
            self::UnitMissingPhoto => 'health.issue.unit_photo',
            self::UnitMissingGps => 'health.issue.unit_missing_gps',
            self::UnitPublicReportsDisabled => 'health.issue.unit_public_reports_disabled',
            self::InactiveDocument => 'health.issue.inactive_document',
            self::CategoryMissingTeam => 'health.issue.category_team',
            self::LocationMissingAddress => 'health.issue.location_address',
        };
    }
}
