<?php

declare(strict_types=1);

namespace App\Support\Units;

use App\Models\Unit;

final class UnitDeletionGuard
{
    public const BLOCK_HAS_ISSUES = 'has_issues';

    public const BLOCK_HAS_TASKS = 'has_tasks';

    public static function canDelete(Unit $unit): bool
    {
        return self::blockReason($unit) === null;
    }

    public static function blockReason(Unit $unit): ?string
    {
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
            default => 'locations.units.delete_blocked',
        };
    }
}
