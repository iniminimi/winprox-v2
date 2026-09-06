<?php

namespace App\Actions\Time;

use App\Models\Worker;
use Illuminate\Support\Facades\Hash;

class ConfirmWorkerClockPinAction
{
    public function handle(Worker $worker, string $pin): ?Worker
    {
        $hash = (string) $worker->clock_pin_hash;
        if ($hash === '') {
            return null;
        }

        $pin = trim($pin);
        if (! preg_match('/^\d{4}$/', $pin)) {
            return null;
        }

        return Hash::check($pin, $hash) ? $worker : null;
    }
}
