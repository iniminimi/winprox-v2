<?php

declare(strict_types=1);

namespace App\Enums;

enum UnitAlbumSendStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case BlockedUnsubscribed = 'blocked_unsubscribed';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Maps onto shared `.wp-pill` variants.
     */
    public function pillVariant(): string
    {
        return match ($this) {
            self::Queued => 'new',
            self::Sent => 'done',
            self::BlockedUnsubscribed, self::Failed => 'closed',
        };
    }
}
