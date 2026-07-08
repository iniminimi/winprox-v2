<?php

declare(strict_types=1);

namespace App\Support\Units;

use App\Models\EsgMeasurement;
use App\Models\Unit;

final class UnitDeletionGuard
{
    public const BLOCK_HAS_ISSUES = 'has_issues';

    public const BLOCK_HAS_TASKS = 'has_tasks';

    public const BLOCK_HAS_ESG_MEASUREMENTS = 'has_esg_measurements';

    public static function canDelete(Unit $unit): bool
    {
        return self::blockReason($unit) === null;
    }

    public static function blockReason(Unit $unit): ?string
    {
        if (EsgMeasurement::query()->where('unit_id', $unit->id)->exists()) {
            return self::BLOCK_HAS_ESG_MEASUREMENTS;
        }

        if ($unit->issues()->exists()) {
            return self::BLOCK_HAS_ISSUES;
        }

        if ($unit->issues()->whereHas('tasks')->exists()) {
            return self::BLOCK_HAS_TASKS;
        }

        return null;
    }

    public static function blockMessageKey(?string $blockReason): string
    {
        return match ($blockReason) {
            self::BLOCK_HAS_ISSUES => 'locations.units.delete_blocked_issues',
            self::BLOCK_HAS_TASKS => 'locations.units.delete_blocked_tasks',
            self::BLOCK_HAS_ESG_MEASUREMENTS => 'locations.units.delete_blocked_esg_measurements',
            default => 'locations.units.delete_blocked',
        };
    }
}
