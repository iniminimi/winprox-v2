<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Prio1 = 'prio_1';
    case Prio2 = 'prio_2';
    case Prio3 = 'prio_3';
    case Prio4 = 'prio_4';

    /** Vertaalsleutel (lang/[locale]/tasks.json -> tasks.priority.*). */
    public function label(): string
    {
        return __('tasks.priority.'.$this->value);
    }

    /** CSS-badge class (.wp-badge-*). */
    public function badgeClass(): string
    {
        return match ($this) {
            self::Prio1 => 'wp-badge-critical',
            self::Prio2 => 'wp-badge-danger',
            self::Prio3 => 'wp-badge-secondary',
            self::Prio4 => 'wp-badge-info',
        };
    }

    /** Sorteerwaarde voor business logica (1 = hoogste prioriteit). */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Prio1 => 1,
            self::Prio2 => 2,
            self::Prio3 => 3,
            self::Prio4 => 4,
        };
    }

    /** Icoon naam voor wp-icon component. */
    public function icon(): string
    {
        return match ($this) {
            self::Prio1 => 'alert-triangle',
            self::Prio2 => 'chevron-up',
            self::Prio3 => 'circle',
            self::Prio4 => 'chevron-down',
        };
    }
}
