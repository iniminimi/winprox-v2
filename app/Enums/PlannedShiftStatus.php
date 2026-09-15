<?php

namespace App\Enums;

enum PlannedShiftStatus: string
{
    case Draft = 'draft';
    case Published = 'published';

    public function isPublished(): bool
    {
        return $this === self::Published;
    }
}
