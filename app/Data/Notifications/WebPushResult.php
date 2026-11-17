<?php

namespace App\Data\Notifications;

final class WebPushResult
{
    public function __construct(
        public bool $delivered,
        public bool $gone,
    ) {}

    public static function delivered(): self
    {
        return new self(true, false);
    }

    public static function gone(): self
    {
        return new self(false, true);
    }

    public static function failed(): self
    {
        return new self(false, false);
    }
}
