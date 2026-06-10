<?php

namespace App\Actions\Units;

use App\Models\Unit;

class EnsureUnitQrTokenAction
{
    public function handle(Unit $unit): string
    {
        $token = is_string($unit->qr_token) ? trim($unit->qr_token) : '';

        if ($token !== '') {
            return $token;
        }

        $token = Unit::generateUniqueQrToken();
        $unit->forceFill(['qr_token' => $token])->saveQuietly();

        return $token;
    }
}
