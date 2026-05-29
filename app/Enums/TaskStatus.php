<?php

namespace App\Enums;

/**
 * De enige vier WinProx-statussen. Leven op de Task; de Issue leidt zijn
 * status hieruit af (zie Issue::recalculateStatus()).
 */
enum TaskStatus: string
{
    case New = 'new';            // Nieuw (Open)
    case InProgress = 'in_progress'; // In uitvoering
    case Done = 'done';          // Afgehandeld
    case Closed = 'closed';      // Gesloten

    /** Vertaalsleutel (lang/[locale]/common.json -> common.status.*). */
    public function labelKey(): string
    {
        return 'common.status.'.$this->value;
    }

    /** CSS-pilvariant (.wp-pill--*). */
    public function pillModifier(): string
    {
        return match ($this) {
            self::New => 'new',
            self::InProgress => 'progress',
            self::Done => 'done',
            self::Closed => 'closed',
        };
    }
}
