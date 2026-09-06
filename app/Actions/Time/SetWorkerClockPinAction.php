<?php

namespace App\Actions\Time;

use App\Models\Worker;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class SetWorkerClockPinAction
{
    public function handle(Worker $worker, string $pin): Worker
    {
        $pin = trim($pin);
        if (! preg_match('/^\d{4}$/', $pin)) {
            throw new InvalidArgumentException('clock_pin_invalid');
        }

        $worker->forceFill([
            'clock_pin_hash' => Hash::make($pin),
            'field_icon_failed_attempts' => 0,
            'field_icon_locked_at' => null,
        ])->save();

        return $worker->fresh();
    }
}
