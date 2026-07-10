<?php

namespace App\Support\Time;

use App\Models\ClockPoint;
use App\Models\ClockPointQrToken;

final class ClockPointPortalTokenResolution
{
    public const STATUS_CURRENT = 'current';
    public const STATUS_GRACE = 'grace';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_NOT_FOUND = 'not_found';

    private function __construct(
        public readonly string $status,
        public readonly ?ClockPoint $clockPoint,
        public readonly ?ClockPointQrToken $historyToken,
    ) {}

    public static function current(ClockPoint $clockPoint): self
    {
        return new self(self::STATUS_CURRENT, $clockPoint, null);
    }

    public static function grace(ClockPoint $clockPoint, ClockPointQrToken $historyToken): self
    {
        return new self(self::STATUS_GRACE, $clockPoint, $historyToken);
    }

    public static function blocked(ClockPoint $clockPoint, ClockPointQrToken $historyToken): self
    {
        return new self(self::STATUS_BLOCKED, $clockPoint, $historyToken);
    }

    public static function notFound(): self
    {
        return new self(self::STATUS_NOT_FOUND, null, null);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, [self::STATUS_CURRENT, self::STATUS_GRACE], true);
    }
}
