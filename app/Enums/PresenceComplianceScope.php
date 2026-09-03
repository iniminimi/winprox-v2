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

    /**
     * @return list<self>
     */
    public static function availableCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $scope): bool => $scope->isAvailable(),
        ));
    }

    public function settingsLabelKey(): string
    {
        return match ($this) {
            self::CiaoCleaning => 'settings.presence.scope_cleaning',
            self::CiaoConstruction => 'settings.presence.scope_construction',
        };
    }
}
